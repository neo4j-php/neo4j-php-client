<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Bolt\error\ConnectException as BoltConnectException;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionRunRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\DateTime as Neo4jDateTime;
use Laudis\Neo4j\Types\DateTimeZoneId as Neo4jDateTimeZoneId;
use Laudis\Neo4j\Types\UnsupportedType;
use Laudis\Neo4j\Types\Vector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Types\OGMTypesAlias
 *
 * @template T of \Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest|\Laudis\Neo4j\TestkitBackend\Requests\TransactionRunRequest
 *
 * @implements RequestHandlerInterface<T>
 */
abstract class AbstractRunner implements RequestHandlerInterface
{
    protected MainRepository $repository;
    private LoggerInterface $logger;

    public function __construct(MainRepository $repository, LoggerInterface $logger)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    public function handle($request): ResultResponse|DriverErrorResponse
    {
        $session = $this->getRunner($request);
        $id = Uuid::v4();

        try {
            $params = [];
            if ($request->getParams() !== null) {
                foreach ($request->getParams() as $key => $value) {
                    $params[$key] = self::decodeToValue($value);
                }
            }

            if ($request instanceof SessionRunRequest && $session instanceof SessionInterface) {
                $metaData = $request->getTxMeta();
                $actualMeta = [];
                if ($metaData !== null) {
                    foreach ($metaData as $key => $meta) {
                        $actualMeta[$key] = self::decodeToValue($meta);
                    }
                }
                $config = TransactionConfiguration::default()->withMetadata($actualMeta)->withTimeout($request->getTimeout());

                $result = $session->run($request->getCypher(), $params, $config);
            } else {
                $result = $session->run($request->getCypher(), $params);
            }

            $this->repository->addRecords($id, $result);

            return new ResultResponse($id, $result->keys());
        } catch (Neo4jException $exception) {
            if ($request instanceof SessionRunRequest) {
                return new DriverErrorResponse($request->getSessionId(), $exception);
            }
            if ($request instanceof TransactionRunRequest) {
                $response = new DriverErrorResponse($request->getTxId(), $exception);
                $this->repository->addRecords($request->getTxId(), $response);

                return $response;
            }

            throw new Exception('Unhandled neo4j exception for run request of type: '.get_class($request));
        } catch (TransactionException $exception) {
            if ($request instanceof TransactionRunRequest) {
                $response = new DriverErrorResponse($request->getTxId(), $exception);
                $this->repository->addRecords($request->getTxId(), $response);

                return $response;
            }

            throw new Exception('Unhandled neo4j exception for run request of type: '.get_class($request));
        } catch (BoltConnectException $e) {
            // Wrap connection/timeout errors for testkit protocol - tests expect DriverError with Neo4jException
            $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage());
            $wrapped = new Neo4jException([$neo4jError], $e);

            if ($request instanceof SessionRunRequest) {
                return new DriverErrorResponse($request->getSessionId(), $wrapped);
            }
            if ($request instanceof TransactionRunRequest) {
                return new DriverErrorResponse($request->getTxId(), $wrapped);
            }

            throw new Exception('Unhandled connection exception for run request of type: '.get_class($request));
        }
        // Unhandled exceptions propagate to Backend's top-level catch and become BackendError (matches Java driver)
    }

    /**
     * @param array{name: string, data: array{value: iterable|scalar|null}} $param
     *
     * @return scalar|AbstractCypherObject|iterable|null
     */
    public static function decodeToValue(array $param)
    {
        if ($param['name'] === 'CypherVector') {
            /** @var array{dtype: string, data: string} $d */
            $d = $param['data'];

            return Vector::fromWire((string) $d['dtype'], (string) $d['data']);
        }
        if ($param['name'] === 'CypherUnsupportedType') {
            /** @var array{name: string, minimumProtocol?: string, minimum_protocol?: string, message?: string|null} $d */
            $d = $param['data'];
            $minProto = $d['minimumProtocol'] ?? $d['minimum_protocol'] ?? '';
            $msg = $d['message'] ?? null;

            return new UnsupportedType(
                (string) $d['name'],
                (string) $minProto,
                $msg !== null ? (string) $msg : null,
            );
        }
        if ($param['name'] === 'CypherDateTime') {
            /** @var array<string, mixed> $d */
            $d = $param['data'];

            return self::decodeCypherDateTime($d);
        }

        $value = $param['data']['value'];
        if (is_iterable($value)) {
            if ($param['name'] === 'CypherMap') {
                /** @psalm-suppress MixedArgumentTypeCoercion */
                $map = [];
                /**
                 * @var numeric $k
                 * @var mixed   $v
                 */
                foreach ($value as $k => $v) {
                    /** @psalm-suppress MixedArgument */
                    $map[(string) $k] = self::decodeToValue($v);
                }

                return new CypherMap($map);
            }

            if ($param['name'] === 'CypherList') {
                $list = [];
                /**
                 * @var mixed $v
                 */
                foreach ($value as $v) {
                    /** @psalm-suppress MixedArgument */
                    $list[] = self::decodeToValue($v);
                }

                return new CypherList($list);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $d
     */
    private static function decodeCypherDateTime(array $d): Neo4jDateTime|Neo4jDateTimeZoneId
    {
        $year = (int) $d['year'];
        $month = (int) $d['month'];
        $day = (int) $d['day'];
        $hour = (int) $d['hour'];
        $minute = (int) $d['minute'];
        $second = (int) $d['second'];
        $nanosecond = (int) $d['nanosecond'];
        /** @var string|null $timezoneId */
        $timezoneId = $d['timezone_id'] ?? null;
        $utcOffsetS = array_key_exists('utc_offset_s', $d) ? $d['utc_offset_s'] : null;

        if ($timezoneId !== null && $timezoneId !== '') {
            $tz = new DateTimeZone($timezoneId);
        } else {
            $tz = self::timezoneFromUtcOffsetSeconds((int) ($utcOffsetS ?? 0));
        }

        $microPart = intdiv($nanosecond, 1000);
        $formatted = sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d.%06d',
            $year,
            $month,
            $day,
            $hour,
            $minute,
            $second,
            $microPart
        );
        $immutable = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $formatted, $tz);
        if ($immutable === false) {
            throw new InvalidArgumentException('Invalid CypherDateTime wall clock');
        }

        $utc = $immutable->setTimezone(new DateTimeZone('UTC'));
        $unixSeconds = (int) $utc->format('U');
        $nanoseconds = (int) $utc->format('u') * 1000 + ($nanosecond % 1000);

        if ($timezoneId !== null && $timezoneId !== '') {
            return new Neo4jDateTimeZoneId($unixSeconds, $nanoseconds, $timezoneId);
        }

        $tzOffsetSeconds = $immutable->getOffset();

        return new Neo4jDateTime($unixSeconds, $nanoseconds, $tzOffsetSeconds, false);
    }

    private static function timezoneFromUtcOffsetSeconds(int $offsetSeconds): DateTimeZone
    {
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $abs = abs($offsetSeconds);
        $h = intdiv($abs, 3600);
        $m = intdiv($abs % 3600, 60);

        return new DateTimeZone($sign.sprintf('%02d:%02d', $h, $m));
    }

    /**
     * @param T $request
     *
     * @return SessionInterface<SummarizedResult<CypherMap<OGMTypes>>>|TransactionInterface<SummarizedResult<CypherMap<OGMTypes>>>
     */
    abstract protected function getRunner($request);

    /**
     * @param T $request
     */
    abstract protected function getId($request): Uuid;
}

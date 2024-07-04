<?php

namespace Laudis\Neo4j\Results;

use Bolt\enum\Signature;
use Bolt\protocol\IStructure;
use Iterator;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\Messages\Pull;
use Laudis\Neo4j\Bolt\Responses\Record;
use Laudis\Neo4j\Bolt\Responses\ResultSuccessResponse;
use Laudis\Neo4j\Bolt\Responses\RunResponse;
use Laudis\Neo4j\Databags\ResultSummary;
use Laudis\Neo4j\Exception\NoSuchRecordException;

/**
 * @implements Iterator<int<0, max>, CombinedRecord>
 */
final class ResultCursor implements Iterator
{
    private int $position = -1;
    private CombinedRecord|null $current = null;

    private ResultSuccessResponse|null $latestResultSuccessResponse = null;

    public function __construct(
        private readonly BoltConnection $connection,
        private readonly Pull           $pull,
        private readonly RunResponse    $runResponse,
    ) {
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->runResponse->fields;
    }

    /**
     * Return the first record in the result, failing if there is not exactly
     * one record left in the stream
     * <p>
     * Calling this method always exhausts the result, even when {@link NoSuchRecordException} is thrown.
     *
     * @return Record the first and only record in the stream
     * @throws NoSuchRecordException if there is not exactly one record left in the stream
     */
    public function single(): CombinedRecord {

    }

    public function only(): null|int|float|array|bool|string|IStructure
    {
        return $this->single()->single();
    }

    /**
     * Retrieve and store the entire result stream.
     * This can be used if you want to iterate over the stream multiple times or to store the
     * whole result for later use.
     * <p>
     * Note that this method can only be used if you know that the query that
     * yielded this result returns a finite stream. Some queries can yield
     * infinite results, in which case calling this method will lead to running
     * out of memory.
     * <p>
     * Calling this method exhausts the result.
     *
     * @return list of all remaining immutable records
     */
     public function toArray(): array {
         return iterator_to_array($this);
     }

    /**
     * Return the result summary.
     * <p>
     * If the records in the result is not fully consumed, then calling this method will exhausts the result.
     * <p>
     * If you want to access unconsumed records after summary, you shall use {@link Result#list()} to buffer all records into memory before summary.
     *
     * @return a summary for the whole query result.
     */
    public function consume(): ResultSummary
    {

    }

    /**
     * Determine if result is open.
     * <p>
     * Result is considered to be open if it has not been consumed ({@link #consume()}) and its creator object (e.g. session or transaction) has not been closed
     * (including committed or rolled back).
     * <p>
     * Attempts to access data on closed result will produce {@link ResultConsumedException}.
     *
     * @return {@code true} if result is open and {@code false} otherwise.
     */
    public function isOpen(): bool
    {

    }

    public function current(): CombinedRecord
    {
        return $this->current;
    }

    public function next(): void
    {
        $response = $this->connection->getResponse();

        if ($response->signature === Signature::RECORD) {
            $this->current = new CombinedRecord($this->runResponse, new Record($response->content));
        } elseif ($response->signature === Signature::SUCCESS) {
            $this->latestResultSuccessResponse = new ResultSuccessResponse(

            );
        }
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return $this->position >= 0 && $this->current === null;
    }

    public function rewind(): void
    {

    }
}

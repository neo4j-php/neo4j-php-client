<?php

/*
 * This file is part of the Laudis api package
 *
 * (c) Laudis <https://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Townnews\UserStitch\Model;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\ParameterHelper;
use function addslashes;
use function fopen;
use function fwrite;
use function is_resource;
use function iterator_to_array;
use function join;
use function json_decode;
use function json_encode;
use function microtime;
use function round;
use function rtrim;
use function str_replace;
use function substr;

class Neo4J
{
    private $oClient;
    private $rOutput;
    private $oTSX;

    public function __construct(array $kConfig)
    {
        /**
         * @todo I would try and use Dependency Injection to for example create a global client or session so we are sure it does not get created many times, constantly repeating the routing mechanism.
         */
        $sURI = 'neo4j+s://'.$kConfig['host'];
        $oCreds = Authenticate::basic($kConfig['user'], $kConfig['pass']);

        $oBuilder = ClientBuilder::create()
            ->withDriver('aura', $sURI, $oCreds);

        if ($kConfig['debug'] ?? false) {
            $oBuilder = $oBuilder->withFormatter(SummarizedResultFormatter::create());
        }

        /**
         * @todo I tend to move the transaction outside of the repository and pass it as a parameter.
         * This way you have more fine-grained control
         * I also see quite a lot of public methods using other transactions. This means the transaction might be constructed without needing it. Starting a transaction involves a network call and can be very slow.
         */
        $this->oClient = $oBuilder->build();
        $this->oTSX = $this->oClient->beginTransaction();
    }

    public function __destruct()
    {
        /**
         * @todo this kan have many unintended consequences. The destructor will be called in case of an uncaught error, possibly committing half of a transaction.
         */
        if ($this->oTSX) {
            $this->oTSX->commit();
        }
    }

    public static function factory(): self
    {
        return new self([
            'user' => $_ENV['NEO4J_USER'],
            'host' => $_ENV['NEO4J_DB'],
            'pass' => $_ENV['NEO4J_PASS'],
            'debug' => $_ENV['NEO4J_DEBUG'] ?? false,
        ]);
    }

    public function getClient()
    {
        return $this->oClient;
    }

    /**
     * Setup Neo4J database constaints.
     *
     * This method can also be ran at any time to make sure that
     * all constraints and indexes are in place as expected.
     */
    public function initializeConstraints(): void
    {
        /**
         * @todo this needs to be put in a command or something to make sure it does not get called every time.
         */
        $this->oClient->writeTransaction(static function (TransactionInterface $oTSX) {
            // Accounts will be made globally unique in the database

            $oTSX->run('CREATE CONSTRAINT udx_account_id IF NOT EXISTS
            FOR (a:Account) REQUIRE a.id IS UNIQUE');

            // API keys will be made globally unique in the database

            $oTSX->run('CREATE CONSTRAINT udx_apikey_key IF NOT EXISTS
            FOR (k:APIKey) REQUIRE k.key IS UNIQUE');

            // User identifiers must be globally unique in the database

            $oTSX->run('CREATE CONSTRAINT udx_user_id IF NOT EXISTS
            FOR (u:User) REQUIRE u.id IS UNIQUE');

            // Identifiers will be made globally unique in the database

            $oTSX->run('CREATE CONSTRAINT udx_identifier_id IF NOT EXISTS
            FOR (i:Identifier) REQUIRE i.id IS UNIQUE');
        });
    }

    public function addUser(User $oUser): void
    {
        $fStart = microtime(true);

        $this->oTSX->run('MATCH (a:Account {id:$accountId})
               MERGE (u:User {id:$id})
                  ON CREATE SET u.created = datetime(), u.traits = $traits
                  ON MATCH SET u.updated = datetime(), u.traits = $traits
               MERGE (u)-[:IS_USER]->(a)', [
            'accountId' => $oUser->account->id,
            'id' => $oUser->id,
            'traits' => json_encode($oUser->traits),
        ]);

        $this->log('AddUser query time (ms): '.$this->timeInMS($fStart));
    }

    public function removeUsers(array $aUsers): void
    {
        $aUserList = [];
        foreach ($aUsers as $oUser) {
            $aUserList[] = $oUser->id;
        }

        if (empty($aUserList)) {
            return;
        }

        $fStart = microtime(true);

        $oState = $this->oTSX->run('MATCH (u:User) WHERE u.id IN $userList DETACH DELETE (u)', [
            'userList' => ParameterHelper::asList($aUserList),
        ]);

        /**
         * @todo it would be nice to have some numbers myself to see if the query time is longer than expected. In case of aura, it should be less then 100 ms.
         */
        $this->log('removeUsers query time (ms): '.$this->timeInMS($fStart));
        $this->debugOutput($oState);
    }

    public function findUsersByIdentifiers(array $aIdentifiers): \Generator
    {
        foreach ($aIdentifiers as $oIdentifier) {
            $aIdList[] = $oIdentifier->computedId;
            $oAccount = $oIdentifier->account;
        }

        $fStart = microtime(true);

        $oResults = $this->oTSX->run('MATCH (u:User)<-[:IS_ALIAS]-(i:Identifier)
            WHERE i.id IN $id_list RETURN DISTINCT u ORDER BY u.created ASC', [
            'id_list' => ParameterHelper::asList($aIdList),
        ]);

        $this->log('findUsersByIdentifiers query time (ms): '.$this->timeInMS($fStart));
        $this->debugOutput($oResults);

        $aUsers = [];
        foreach ($oResults as $oResult) {
            $oUserResult = $oResult->get('u');
            $oUser = new User($oAccount);
            $oUser->id = $oUserResult->id;
            $oUser->traits = json_decode($oUserResult->traits);

            yield $oUser;
        }
    }

    public function addIdentifier(Identifier $oIdentifier, User $oUser): void
    {
        $fStart = microtime(true);

        $oState = $this->oTSX->run('
            MATCH (u:User {id:$userId})
            MERGE (i:Identifier {id:$id})
                ON CREATE SET i.created = datetime(), i.typeOfId = $type, i.rawId = $rawId
                ON MATCH SET i.updated = datetime(), i.typeOfId = $type, i.rawId = $rawId
            MERGE (i)-[:IS_ALIAS]->(u)
                ON MATCH SET
                u.traits=$traits', [
            'userId' => $oUser->id,
            'id' => $oIdentifier->computedId,
            'type' => $oIdentifier->typeOfId,
            'rawId' => $oIdentifier->rawId,
            'traits' => json_encode($oUser->traits),
        ]);

        $this->log('AddIdentifier query time (ms): '.$this->timeInMS($fStart));
        $this->debugOutput($oState);
    }

    public function updateAccount(Account $oAccount): void
    {
        $oTSX = $this->oClient->beginTransaction();

        try {
            $this->oTSX->run('MERGE (a:Account {id:$id,name:$name})
           ON CREATE SET
              a.created = datetime()
            ON MATCH SET
              a.updated = datetime()', [
                'id' => $oAccount->id,
                'name' => $oAccount->name,
            ]);

            $oTSX->commit();
        } catch (\Throwable $oError) {
            $oTSX->rollback();
            throw $oError;
        }
    }

    /**
     * Retrieves an account by ID.
     *
     * @param string $sID      The ID of the account to query for
     * @param bool   $bDeleted Return an account even if marked for removal
     *
     * @return ?Account Returns an account object if found
     */
    public function getAccount(string $sID, bool $bDeleted = false): ?Account
    {
        $oResults = $this->searchAccounts([
            'id' => $sID,
            'includeDeleted' => $bDeleted,
            'limit' => 1,
        ]);

        return $oResults->current();
    }

    public function searchAccounts(array $kSearch): \Generator
    {
        $fStart = microtime(true);

        if (!empty($kSearch['apikey'])) {
            $sQuery = 'MATCH (a:Account)<-[IS_API_KEY]-(APIKey {key:$apikey}) ';
        } else {
            $sQuery = 'MATCH (a:Account) ';
        }
        $aCond = [];

        if (empty($kSearch['includeDeleted'])) {
            $aCond[] = 'NOT EXISTS(a.deleteTime)';
        }

        if (!empty($kSearch['name'])) {
            if (substr($kSearch['name'], -1) == '*') {
                $kSearch['name'] = rtrim($kSearch['name'], '*');
                $aCond[] = 'a.name STARTS WITH $name';
            } else {
                $aCond[] = 'a.name = $name';
            }
        }

        if (!empty($kSearch['id'])) {
            if (substr($kSearch['id'], -1) == '*') {
                $kSearch['id'] = rtrim($kSearch['id'], '*');
                $aCond[] = 'a.id STARTS WITH $id';
            } else {
                $aCond[] = 'a.id = $id';
            }
        }

        if (!empty($aCond)) {
            $sQuery .= ' WHERE '.join(' AND ', $aCond);
        }

        $sQuery .= ' RETURN a ';

        if (!empty($kSearch['limit'])) {
            $sQuery .= ' LIMIT '.(int) $kSearch['limit'];
        }

        $oResults = $this->oTSX->run($sQuery, [
            'id' => $kSearch['id'] ?? null,
            'name' => $kSearch['name'] ?? null,
            'apikey' => $kSearch['apikey'] ?? null,
        ]);

        $this->log('searchAccounts query time (ms): '.$this->timeInMS($fStart));
        /**
         * @todo I would love to see some of this output myself to see the actual query
         */
        $this->debugOutput($oResults);

        foreach ($oResults as $oProps) {
            $oProps = $oProps->get('a');
            $oAccount = new Account($oProps->id, $oProps->name);
            $oAccount->created = $oProps->created;
            if (!empty($oProps->updated)) {
                $oAccount->updated = $oProps->updated;
            }

            yield $oAccount;
        }
    }

    public function updateAPIKey(APIKey $oKey): void
    {
        $this->oClient->writeTranaction(static function (TransactionInterface $oTSX) use ($oKey) {
            $oTSX->run('MERGE (k:APIKey {key:$key,name:$name})
            ON CREATE SET
              k.created = datetime()
            ON MATCH SET
              k.updated = datetime()', [
                'key' => $oKey->key,
                'name' => $oKey->name ?? 'Default',
            ]);

            $oTSX->run('MATCH
              (k:APIKey),
              (a:Account)
            WHERE
                k.key = $key AND a.id = $accountId
            CREATE
                (k)-[:IS_API_KEY]->(a)', [
                'key' => $oKey->key,
                'accountId' => $oKey->account->id,
            ]);
        });
    }

    public function cleanUp(\DateTimeInterface $oDate)
    {
        /**
         * @todo This looks like a cron job to me and definitely doesn't need to be run during the request lifecycle
         */
        $oTSX = $this->oClient->beginTransaction();
        try {
            // Find all accounts that have been marked for removal and
            // delete all nodes and children

            $oTSX->run('MATCH (a:Account)<-[*0..]-(x)
           WHERE a.deleteTime <= date($date)
           DETACH DELETE x', [
                'date' => $oDate->format(\DateTime::ISO8601),
            ]);

            // Remove orphaned 'users' - they can never be matched

            $oTSX->run('MATCH (u:User) WHERE NOT (u)<-[:IS_ALIAS]-(:Identifier) DETACH DELETE u');

            // Find all abandoned identifiers older than 3 months and remove

            $oTSX->run('MATCH (i:Identifier)
           WHERE (datetime({epochmillis:i.lastSeen}) + duration("P3M")) < datetime($date)
           DETACH DELETE i', [
                'date' => $oDate->format(\DateTime::ISO8601),
            ]);

            $oTSX->commit();
        } catch (\Throwable $oError) {
            $oTSX->rollback();
            throw $oError;
        }
    }

    private function debugOutput($oResults): void
    {
        if ($oResults instanceof SummarizedResult) {
            $oSummary = $oResults->getSummary();

            // Build a compatibility

            $oStmt = $oSummary->getStatement();
            $sQuery = $oStmt->getText();
            foreach ($oStmt->getParameters() as $sParam => $xValue) {
                if ($xValue instanceof \Laudis\Neo4j\Types\CypherList) {
                    $aValues = iterator_to_array($xValue);
                    $sQuery = str_replace('$'.$sParam, '["'.join('","', $aValues).'"]', $sQuery);
                } else {
                    $sQuery = str_replace('$'.$sParam, "'".addslashes((string) $xValue)."'", $sQuery);
                }
            }

            $this->log('Query:'.$sQuery);

            /*
            $this->log('Counters:');
            foreach ($oSummary->getCounters() as $sKey => $xValue) {
                $this->log("{$sKey}: {$xValue}");
            }
            */

            $this->log('---------');
        }
    }

    private function log($sData)
    {
        if (!is_resource($this->rOutput)) {
            $this->rOutput = fopen('php://stderr', 'w');
            if (!is_resource($this->rOutput)) {
                throw new \Exception('Unable to open STDERR');
            }
        }

        fwrite($this->rOutput, $sData.\PHP_EOL);
    }

    private function timeInMS(float $fStart): float
    {
        return round((microtime(true) - $fStart) * 1000);
    }
}

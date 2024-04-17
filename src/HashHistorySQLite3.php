<?php
namespace dynoser\vezdes;

class HashHistorySQLite3
{
    const DEFAULT_HASH_ALG = 'sha256';
    public $maxContentSize = 1048576; //1M 
    private $hashAlgName = self::DEFAULT_HASH_ALG;
    private $hashHexLen = 64;
    
    public $dbTableName = 'hashnamed';
    
    public $sqliteFileName;
    public $sqlite;
    public $lastSQL;
    public $lastErrCode;
    public $lastErrMsg;
    public $needCommit = false;

    /**
     * Object Constructor
     *
     * @param string $db_file
     * @param string|null $hashAlgName
     */
    public function __construct($db_file, $hashAlgName = self::DEFAULT_HASH_ALG)
    {
        $this->setHashAlg($hashAlgName);
        
        for ($try_cnt = 0; $try_cnt < 5; $try_cnt++) {
            $ret = $this->setSQLite3File($db_file);
            if (!isset($ret['err']) || ($this->lastErrCode != 5)) break;
            \usleep(1000);
        }
    }
    
    /**
     * Set db SQLite3 file
     * 
     * @param string $db_file
     * @return array|null
     */
    public function setSQLite3File($db_file) //: ?array
    {
        $this->sqliteFileName = $db_file;
        $file_exists = \is_file($db_file);
        try {
            $this->sqlite = $db = new \SQLite3($this->sqliteFileName);
        } catch(\Exception $e) {
            return ['error' => $e->getMessage()];
        }
        $this->lastSQL = 'PRAGMA synchronous = OFF';
        if (@$db->exec($this->lastSQL)) {
            $db->exec('PRAGMA temp_store = MEMORY');
            $table_name = $this->dbTableName;
            if (!$file_exists) {
                $this->initStorage();
            }
        } else {
            $this->lastErrCode = $db->lastErrorCode();
            $this->lastErrMsg = $db->lastErrorMsg();
            $this->sqlite = false;
            return ['error' => $this->lastErrMsg, 'code' => $this->lastErrCode];
        }
        return null;
    }
    
    public function __destruct()
    {
        if ($this->sqlite) {
            if ($this->needCommit) {
                $this->commit();
            }
            $this->sqlite->close();
        }
    }

    public function begin()
    {
        if (!$this->needCommit) {
            $db = $this->sqlite;
            if (!$db) return 'db is not open';
            $this->needCommit = true;
            $this->lastSQL = "BEGIN";
            if (!$db->exec($this->lastSQL)) {
                $this->lastErrCode = $db->lastErrorCode();
                $this->lastErrMsg = $db->lastErrorMsg();
                throw new \Exception("BEGIN error: " . $this->lastErrMsg);
            }
        }
        return false;
    }

    public function commit()
    {
        if ($this->needCommit) {
            $db = $this->sqlite;
            if (!$db) {
                throw new \Exception("DB is not open");
            }
            $this->needCommit = false;
            $this->lastSQL = "COMMIT";
            if (!$db->exec($this->lastSQL)) {
                $this->lastErrCode = $db->lastErrorCode();
                $this->lastErrMsg = $db->lastErrorMsg();
                throw new \Exception("COMMIT error: " . $this->lastErrMsg);
            }
        }
        return false;
    }
    
    /**
     * Set hash-algorithm
     *
     * @param string $hashAlgName
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setHashAlg($hashAlgName = null) //: void
    {
        if ($hashAlgName && $hashAlgName !== self::DEFAULT_HASH_ALG) {
            $testHash = \hash($hashAlgName, 'test');
            if (!$testHash) {
                throw new \InvalidArgumentException("Hash $hashAlgName is not supported.");
            }
            $this->hashHexLen = strlen($testHash);
        } else {
            $hashAlgName = self::DEFAULT_HASH_ALG;
        }

        $this->hashAlgName = $hashAlgName;
    }
    
    public function getHashAlg() {
        return [$this->hashAlgName, $this->hashHexLen];
    }

    public function initStorage() //: void
    {
        $this->lastSQL = "CREATE TABLE IF NOT EXISTS " . $this->dbTableName . " (
            hash TEXT PRIMARY KEY,
            content BLOB NOT NULL,
            dtm INTEGER NOT NULL
        );";

        if (!$this->sqlite->exec($this->lastSQL)) {
            $this->lastErrCode = $this->sqlite->lastErrorCode();
            $this->lastErrMsg = $this->sqlite->lastErrorMsg();
            throw new \Exception("Initialization error: " . $this->lastErrMsg);
        }
    }

    /**
     * Save content to hash-named cell
     *
     * @param string $newContentData
     * @return string|null
     * @throws \Exception
     */
    public function saveContent($newContentData, $hashHex = '', $dtm = 0)//: ?string
    {
        $newContentSize = \strlen($newContentData);

        if (!$newContentSize || $newContentSize > $this->maxContentSize) {
            return null;
        }
        
        if (!$hashHex) {
            $hashHex = \hash($this->hashAlgName, $newContentData, false);
        }

        $oldRow = $this->readContentByHash($hashHex);
        if ($oldRow) {
            $oldContentData = $oldRow['content'];

            if ($newContentData === $oldContentData) {
                return $hashHex;
            }
            throw new \Exception("Hash conflict");

        } else {
            if (!$dtm) {
                $dtm = time();
            }
            $this->lastSQL = "INSERT INTO " . $this->dbTableName . " (hash, content, dtm) VALUES (:hash, :content, :dtm)";
            $stmt = $this->sqlite->prepare($this->lastSQL);
            $stmt->bindValue(':hash', $hashHex);
            $stmt->bindValue(':content', $newContentData, \SQLITE3_BLOB);
            $stmt->bindValue(':dtm', $dtm);

            if(!$stmt->execute()){
                $this->lastErrCode = $this->sqlite->lastErrorCode();
                $this->lastErrMsg = $this->sqlite->lastErrorMsg();
                throw new \Exception("Write error: " . $this->lastErrMsg);
            }
        }

        return $hashHex;
    }


    /**
     * Read content by hash
     *
     * @param string $hashHex
     * @return array|null
     * @throws \Exception
     */
    public function readContentByHash($hashHex) //: ?array
    {
        if (strlen($hashHex) === $this->hashHexLen) {
            $this->lastSQL = "SELECT content, dtm FROM " . $this->dbTableName . " WHERE hash=:hash";
            
            $stmt = $this->sqlite->prepare($this->lastSQL);
            $stmt->bindValue(':hash', $hashHex);
            $result = $stmt->execute();

            if (!$result) {
                $this->lastErrCode = $this->sqlite->lastErrorCode();
                $this->lastErrMsg = $this->sqlite->lastErrorMsg();
                throw new \Exception("Read error: " . $this->lastErrMsg);
            }

            if ($data = $result->fetchArray(\SQLITE3_ASSOC)) {
                return $data;
            }
        }

        return null;
    }
}

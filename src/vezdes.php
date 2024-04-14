<?php
namespace dynoser\vezdes;

class VezdesHeader
{
    public $spcs = " \n\r\t";
    public $vezdesHELML;
    public function __construct($vezdesHeader) {
        $this->vezdesHELML = $vezdesHeader;
    }
    public function getParStr($parName) {
        $parFind = $parName . ': ';
        $i = \strpos($this->vezdesHELML, $parFind);
        if (false !== $i) {
            $i += \strlen($parFind);
            $l = \strcspn($this->vezdesHELML, $this->spcs, $i);
            return \substr($this->vezdesHELML, $i, $l);
        }
    }
    
    public static function base64Udecode($b64) {
        return \base64_decode(\strtr($b64, '-_', '+/'));
    }
    
    public function getParDeB64($parName) {
        $b64 = $this->getParStr($parName);
        if ($b64) {
            return self::base64Udecode($b64);
        }
    }
    public function getParExp($parName, $expectedLen) {
        $bytes = $this->getParDeB64($parName);
        if ($bytes && \strlen($bytes) !== $expectedLen) {
            http_response_code(403);
            die("Incorrect parameter $parName in VEZDES-header (expected length=$expectedLen bytes base64url-encoded)");
        }
        return $bytes;
    }
}
class VezdesParser {
    public $hWord = 'VEZDES';
    public $bodyStr;
    public $bodyLen;
    public $hObj;
    public $dataStartPos;
    public $dataEndPos;
    public $headerFullStr;
    public $prefix;
    public $postfix;
    
    public $beforeHeaderLen;
    public $afterHeaderPos;
    public $emplStr;
    
    public $pubkey;
    public $signature;
    
    public function __construct($body, $maxBodySize) {
        $this->bodyStr = isset($_REQUEST['body'])?
                $_REQUEST['body'] :
                (empty($body) ? die("POST-body required") : $body);
        $this->bodyLen = \strlen($this->bodyStr);
        if ($this->bodyLen > $maxBodySize) {
            http_response_code(413);
            die("Too large body size={$this->bodyLen} bytes (max=$maxBodySize bytes)");
        }
        $headerPrefix = $this->hWord . ':';
        $this->dataStartPos = $scanPos = \strpos($this->bodyStr, $headerPrefix);
        if ($scanPos) {
            $headerPostfix = '/' . $this->hWord;
            $scanPos += \strlen($headerPrefix);
            $this->dataEndPos = \strpos($this->bodyStr, $headerPostfix, $scanPos);
            if ($this->dataEndPos) {
                $vezdesHELML = \substr($this->bodyStr, $scanPos, $this->dataEndPos - $scanPos);
                $this->dataEndPos += \strlen($headerPostfix);
            }
        }
        if (empty($vezdesHELML)) {
            http_response_code(403);
            die("No VEZDES-header in body (required for auth)");
        }
        $this->hObj = new VezdesHeader($vezdesHELML);
        
        $this->pubkey = $this->hObj->getParExp('PUBKEY', 32);
        $this->signature = $this->hObj->getParExp('SIGNATURE', 64);
        $empl = $this->hObj->getParStr('EMPL');
        $this->emplStr = \str_repeat("\n", \is_numeric($empl) ? (int)$empl : 1);
    }
    
    public function calcHeaderPosition() {
        $spcs = $this->hObj->spcs;
        $docLen = $this->bodyLen;
        
        // scan document len BEFORE header (for outHash calc)
        $beforeHeaderLen = $this->dataStartPos;
        $startFull = $beforeHeaderLen;
        $inQuotes = '';
        $ch = '';
        while ($beforeHeaderLen > 0) {
            $ch = $this->bodyStr[$beforeHeaderLen];
            $inQuotes = ($ch === '"') ? $ch : '';
            if ($ch === "\n" || $ch === "\r" || $inQuotes) {
                 $startFull++;
                 if ($inQuotes) {
                     break;
                 }
                 // skip all spaces before header
                 while ($beforeHeaderLen > 0) {
                     $ch = $this->bodyStr[--$beforeHeaderLen];
                     if (false === \strpos($spcs, $ch)) {
                         $beforeHeaderLen++;
                         break;
                     }
                 }
                 break;
             }
             $beforeHeaderLen--;
             $startFull--;
         }
         // search position AFTER header (for outHash calc)
        $afterHeaderPos = $this->dataEndPos;
        $endFull = $afterHeaderPos + 1;
        while ($afterHeaderPos < $docLen) {
            $ch = $this->bodyStr[$afterHeaderPos];
            if ($ch === "\n" || $ch === "\r" || $ch === $inQuotes) {
                $endFull--;
                if ($ch === $inQuotes) {
                    $afterHeaderPos++;
                    break;
                }
                // skip spaces after header
                while ($afterHeaderPos < $docLen) {
                    $ch = $this->bodyStr[++$afterHeaderPos];
                    if (false === \strpos($spcs, $ch)) {
                        break;
                    }
                }
                break;
            }
            $afterHeaderPos++;
            $endFull++;
        }

        $this->headerFullStr = \substr($this->bodyStr, $startFull, $endFull - $startFull);
        $this->prefix = \trim(\substr($this->bodyStr, $beforeHeaderLen, $this->dataStartPos - $beforeHeaderLen));
        if ($inQuotes) {
            $this->prefix = '';
        }
        $this->postfix = $this->prefix ? \trim(\substr($this->bodyStr, $this->dataEndPos, $afterHeaderPos - $this->dataEndPos)) : '';

        $this->beforeHeaderLen = $beforeHeaderLen;
        $this->afterHeaderPos = $afterHeaderPos;
    }
    
    public function calcOutHash() {
        $beforeHeaderStr = \substr($this->bodyStr, 0, $this->beforeHeaderLen);
        $afterHeaderStr = \substr($this->bodyStr, $this->afterHeaderPos);
        $dataForHash = $beforeHeaderStr . $this->emplStr . $afterHeaderStr;
        if (\strpos($dataForHash, "\r")) {
            $dataForHash = \str_replace("\r\n", "\n", $dataForHash);
            if(\strpos($dataForHash, "\r")) {
                $dataForHash = \strtr($dataForHash, "\r", "\n");
            }
        }
        $outLen = \strlen($dataForHash);
        $hash512 = \hash('sha512', $dataForHash, true);
        return \substr($hash512, 0, 32);
    }
    
    public static function hash512032($dataForHash) {
        $hash512 = \hash('sha512', $dataForHash, true);
        return \substr($hash512, 0, 32);
    }
    
    public function checkSignature($signatureBin = null, $hashBin = null, $pubKeyBin = null) {
        if (!$pubKeyBin) {
            $pubKeyBin = $this->pubkey;
        }
        if (!$hashBin) {
            $hashBin = $this->calcOutHash();
        }
        if (!$signatureBin) {
            $signatureBin = $this->signature;
        }
        return self::signVerifyDetached($signatureBin, $hashBin, $pubKeyBin);
    }

    public static function signVerifyDetached($signatureBin, $hashBin, $pubKeyBin) {
        if (\strlen($pubKeyBin) !== 32 || \strlen($signatureBin) !== 64) {
            return false;
        }
        require 'checkSignFn.php';
        return \sodium_crypto_sign_verify_detached($signatureBin, $hashBin, $pubKeyBin);
    }
}


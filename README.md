# Autoframe is a low level framework that is oriented on SOLID flexibility

[![Build Status](https://github.com/autoframe/components-socket-cache/workflows/PHPUnit-tests/badge.svg)](https://github.com/autoframe/components-socket-cache/actions?query=branch:main)
[![License: The 3-Clause BSD License](https://img.shields.io/github/license/autoframe/components-socket-cache)](https://opensource.org/license/bsd-3-clause/)
![Packagist Version](https://img.shields.io/packagist/v/autoframe/components-socket-cache?label=packagist%20stable)
[![Downloads](https://img.shields.io/packagist/dm/autoframe/components-socket-cache.svg)](https://packagist.org/packages/autoframe/components-socket-cache)

*PHP client - cache server alternative for memcached / redis*


```php

namespace Autoframe\Components\FtpTransfer;
...
class AfrFtpBackupConfig
{
    protected string $sBusinessLogicClass = AfrFtpPutBigData::class; //Class must implement AfrFtpBusinessLogicInterface::class
    public function setBusinessLogic(string $BusinessLogicClass): void {}
    public function getBusinessLogic(): string {}
    
    protected ?string $sReportClass = null;  //Class must implement AfrFtpReportInterface::class
    public function setReportClass(string $sReportClass): void {}
    public function getReportClass(): string {}
    
    public string $sTodayFolderName = 'today';
    public string $sLatestFolderName = '!latest';
    public string $sResumeDump = __DIR__ . DIRECTORY_SEPARATOR . 'self.resume.php';

    /**
     * From local dir path is in key,
     * Ftp destination dir path is into value
     * = [ 'C:\xampp\htdocs\afr\src\FtpBackup' => '/bpg-backup/MG1/test2/resume']
     */
    public array $aFromToPaths;
    
    public string $ConServer; //Server ip or hostname
    public string $ConUsername;
    public string $ConPassword;
    public int $ConPort = 21;
    public int $ConTimeout = 90;
    public bool $ConPassive = true;
    public int $iDirPermissions = 0775;
 
    public string $sReportTarget = '';
    public string $sReportTo = '';
    public string $sReportToSecond = '';
    public string $sReportSubject = 'Ftp upload report';
    public string $sReportBody ;
    public $mReportMixedA = null;
    public $mReportMixedS = null;
    public $mReportMixedI = null;
    public int $iLogUploadProgressEveryXSeconds = 60;

    public function __construct(string $sTodayFolderName = null)
    {
        if ($sTodayFolderName === null) {
            $sTodayFolderName = date('Ymd');
        }
        $this->sTodayFolderName = $sTodayFolderName;
    }

}
```

---

```php
namespace Autoframe\Components\FtpTransfer\Connection;
...
interface AfrFtpConnectionInterface
{
    public function connect();
    public function disconnect(): void;
    public function reconnect(int $iTimeoutMs = 10);
    public function getConnection();
    public function getLoginResult(): bool;
    public function getError(): string;
    public function __destruct();
    public function getDirPerms(): int; //from ftpConfig object
}
```

---

```php
namespace Autoframe\Components\FtpTransfer\FtpBusinessLogic;
...
interface AfrFtpBusinessLogicInterface
{
    public function makeBackup(): void;
}
```

---

```php
namespace Autoframe\Components\FtpTransfer\Log;
...
interface AfrFtpLogInterface
{
    public const FATAL_ERR = 1;
    public const MESSAGE = 2;

    public function newLog(): self;
    public function logMessage(string $sMessage, int $iType): self;
    public function closeLog(): self;
}
```

---

```php
namespace Autoframe\Components\FtpTransfer\Report;
...
interface AfrFtpReportInterface
{
    public function ftpReport(AfrFtpBackupConfig $oFtpConfig): array;
}
```

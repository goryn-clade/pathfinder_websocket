<?php
/**
 * Created by PhpStorm.
 * User: exodu
 * Date: 03.09.2017
 * Time: 17:02
 */

namespace Exodus4D\Socket\Component\Handler;


class LogFileHandler {

    const STREAM_ROOT                   = '/var/www/html/pathfinder/history/map';
    const ERROR_DIR_CREATE              = 'There is no existing directory at "%s" and its not buildable.';
    const ERROR_STREAM_INVALID          = 'Stream path "%s" is outside the allowed root.';

    /**
     * stream dir
     * @var string
     */
    private $dir                        = '.';

    /**
     * file base dir already created
     * @var bool
     */
    private $dirCreated = false;

    public function __construct(private readonly string $stream){
        $this->validateStream();
        $this->dir = dirname($this->stream);
        $this->createDir();
    }

    /**
     * write log data into to file
     * @param array<string, mixed> $log
     */
    public function write(array $log): void {
        $log = (string)json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if( !empty($log) ){
            if($stream = fopen($this->stream, 'a')){
                flock($stream, LOCK_EX);
                fwrite($stream, $log . PHP_EOL);
                flock($stream, LOCK_UN);
                fclose($stream);
            }
        }
    }

    private function validateStream(): void {
        if (str_contains($this->stream, '..')) {
            throw new \InvalidArgumentException(sprintf(self::ERROR_STREAM_INVALID, $this->stream));
        }
        $root = rtrim(self::STREAM_ROOT, '/') . '/';
        if (!str_starts_with($this->stream, $root)) {
            throw new \InvalidArgumentException(sprintf(self::ERROR_STREAM_INVALID, $this->stream));
        }
    }

    /**
     * create directory
     */
    private function createDir(): void {
        // Do not try to create dir if it has already been tried.
        if ($this->dirCreated){
            return;
        }

        if ($this->dir && !is_dir($this->dir)){
            $status = mkdir($this->dir, 0777, true);
            if (false === $status) {
                throw new \UnexpectedValueException(sprintf(self::ERROR_DIR_CREATE, $this->dir));
            }
        }
        $this->dirCreated = true;
    }
}
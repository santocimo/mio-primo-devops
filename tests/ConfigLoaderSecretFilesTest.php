<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/config/loader.php';

final class ConfigLoaderSecretFilesTest extends TestCase
{
    public function testLoadsSecretFromConfiguredFile(): void
    {
        $secretFile = tempnam(sys_get_temp_dir(), 'businessregistry-secret-');
        self::assertNotFalse($secretFile);
        self::assertSame(12, file_put_contents($secretFile, "test-secret\n"));

        $previousSecret = getenv('APP_TOKEN_SECRET');
        $previousSecretFile = getenv('APP_TOKEN_SECRET_FILE');
        putenv('APP_TOKEN_SECRET');
        putenv('APP_TOKEN_SECRET_FILE=' . $secretFile);

        try {
            load_environment_secret_files(['APP_TOKEN_SECRET']);
            self::assertSame('test-secret', getenv('APP_TOKEN_SECRET'));
        } finally {
            $this->restoreEnvironment('APP_TOKEN_SECRET', $previousSecret);
            $this->restoreEnvironment('APP_TOKEN_SECRET_FILE', $previousSecretFile);
            unlink($secretFile);
        }
    }

    public function testFailsClearlyWhenConfiguredSecretFileIsMissing(): void
    {
        $previousSecret = getenv('APP_TOKEN_SECRET');
        $previousSecretFile = getenv('APP_TOKEN_SECRET_FILE');
        putenv('APP_TOKEN_SECRET');
        putenv('APP_TOKEN_SECRET_FILE=/path/that/does/not/exist');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('APP_TOKEN_SECRET');
            load_environment_secret_files(['APP_TOKEN_SECRET']);
        } finally {
            $this->restoreEnvironment('APP_TOKEN_SECRET', $previousSecret);
            $this->restoreEnvironment('APP_TOKEN_SECRET_FILE', $previousSecretFile);
        }
    }

    /**
     * @param string|false $value
     */
    private function restoreEnvironment(string $name, $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv($name . '=' . $value);
    }
}

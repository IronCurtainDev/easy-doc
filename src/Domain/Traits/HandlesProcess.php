<?php

declare(strict_types=1);

namespace EasyDoc\Domain\Traits;

use Symfony\Component\Process\Process;

/**
 * Trait for handling shell process commands.
 */
trait HandlesProcess
{
    /**
     * Verify pre-requisite software exists.
     */
    protected static function verifyRequiredCommandsExist(array $list): bool
    {
        foreach ($list as $command => $name) {
            $process = Process::fromShellCommandline($command);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException("{$name} not found. This is required to proceed. Check by typing `{$command}` and press enter.");
            }
        }

        return true;
    }

    /**
     * Run a shell command and return the process.
     */
    protected static function runCommand(string $command, ?string $cwd = null, int $timeout = 60): Process
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }

    /**
     * Run a shell command that must succeed.
     */
    protected static function runCommandMustSucceed(string $command, ?string $cwd = null, int $timeout = 60): Process
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout($timeout);
        $process->mustRun();

        return $process;
    }
}

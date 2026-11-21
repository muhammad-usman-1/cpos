<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Sets the developer login (username and password) in the .env file. Only someone with access to the
 * server can run it, so the shop owner cannot lock the developer out.
 */
#[Signature('developer:password {--username= : Developer username} {--password= : New password (asked for when left out)}')]
#[Description('Set the developer login used to reset the owner\'s password')]
class DeveloperPassword extends Command
{
    public function handle(): int
    {
        $username = trim((string) ($this->option('username') ?: $this->ask('Developer username', config('developer.username') ?: 'developer')));
        $password = (string) ($this->option('password') ?: $this->secret('New developer password (at least 10 characters)'));

        if ($username === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $this->error('Use letters, numbers, dots, dashes or underscores for the username.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 10) {
            $this->error('The password must be at least 10 characters.');

            return self::FAILURE;
        }

        $this->writeEnv([
            'DEVELOPER_USERNAME' => $username,
            'DEVELOPER_PASSWORD_HASH' => "'".Hash::make($password)."'",
        ]);

        Artisan::call('config:clear');
        $this->info("Developer login saved for \"{$username}\". Open /developer/login to use it.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $env = file_exists($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $env = preg_match($pattern, $env)
                ? (string) preg_replace_callback($pattern, fn () => $line, $env)
                : rtrim($env, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $env);
    }
}

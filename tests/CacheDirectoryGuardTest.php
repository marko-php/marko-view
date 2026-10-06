<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Exceptions\ViewException;

beforeEach(function (): void {
    $this->base = sys_get_temp_dir() . '/marko-view-guard-' . bin2hex(random_bytes(8));
    mkdir($this->base, 0o700);
    $this->guard = new CacheDirectoryGuard(new ProjectPaths($this->base));
});

afterEach(function (): void {
    $remove = function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            chmod($path, 0o700);

            foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
                $remove($path . '/' . $entry);
            }

            rmdir($path);

            return;
        }

        unlink($path);
    };

    $remove($this->base);
});

it('resolves the default storage/views under the project base path, not the working directory', function (): void {
    $directory = $this->guard->prepare('storage/views');

    expect($directory)->toBe($this->base . '/storage/views')
        ->and(is_dir($directory))->toBeTrue();
});

it('creates a missing cache directory with mode 0700', function (): void {
    $directory = $this->guard->prepare('storage/views');

    expect(fileperms($directory) & 0o777)->toBe(0o700);
});

it('leaves an absolute cache directory path unchanged', function (): void {
    $absolute = $this->base . '/absolute-cache';

    expect($this->guard->prepare($absolute . '/'))->toBe($absolute)
        ->and(is_dir($absolute))->toBeTrue();
});

it('accepts an existing private directory owned by the current user', function (): void {
    $directory = $this->base . '/existing';
    mkdir($directory, 0o755);

    expect($this->guard->prepare($directory))->toBe($directory);
});

it('refuses a world-writable cache directory such as a pre-created /tmp/views', function (): void {
    $directory = $this->base . '/shared';
    mkdir($directory);
    chmod($directory, 0o777);

    expect(fn (): string => $this->guard->prepare($directory))
        ->toThrow(InsecureCacheDirectoryException::class, 'world-writable');
});

it('reports the directory mode and a chmod fix when refusing a world-writable directory', function (): void {
    $directory = $this->base . '/sticky';
    mkdir($directory);
    chmod($directory, 0o1777);

    try {
        $this->guard->prepare($directory);
        $this->fail('Expected InsecureCacheDirectoryException');
    } catch (InsecureCacheDirectoryException $exception) {
        expect($exception)->toBeInstanceOf(ViewException::class)
            ->and($exception->getContext())->toContain('1777')
            ->and($exception->getSuggestion())->toContain("chmod 0700 $directory");
    }
});

it('refuses a cache path that exists as a file', function (): void {
    $file = $this->base . '/not-a-dir';
    file_put_contents($file, '');

    expect(fn (): string => $this->guard->prepare($file))
        ->toThrow(InsecureCacheDirectoryException::class, 'is not a directory');
});

it('throws loudly when the cache directory cannot be created', function (): void {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $this->markTestSkipped('root can create directories inside read-only parents');
    }

    $readOnly = $this->base . '/read-only';
    mkdir($readOnly, 0o500);

    expect(fn (): string => $this->guard->prepare($readOnly . '/views'))
        ->toThrow(InsecureCacheDirectoryException::class, 'Unable to create');
});

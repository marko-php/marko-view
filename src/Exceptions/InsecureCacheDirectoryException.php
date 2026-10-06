<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

class InsecureCacheDirectoryException extends ViewException
{
    public static function cannotCreate(
        string $directory,
        ?string $reason,
    ): self {
        return new self(
            message: "Unable to create the compiled-template cache directory '$directory'",
            context: 'Template engines compile views to PHP files in view.cache_directory and include them.'
                . ($reason !== null ? " mkdir() reported: $reason" : ''),
            suggestion: 'Create the directory yourself (mode 0700, owned by the web server user) '
                . "or point view.cache_directory at a writable location such as 'storage/views'",
        );
    }

    public static function notADirectory(
        string $directory,
    ): self {
        return new self(
            message: "The compiled-template cache path '$directory' exists but is not a directory",
            context: 'Template engines compile views to PHP files in view.cache_directory and include them.',
            suggestion: "Remove the file or point view.cache_directory at a directory such as 'storage/views'",
        );
    }

    public static function worldWritable(
        string $directory,
        int $permissions,
    ): self {
        $mode = sprintf('%04o', $permissions & 0o7777);

        return new self(
            message: "Refusing to use world-writable compiled-template cache directory '$directory'",
            context: "Directory mode is $mode. Compiled templates are PHP files that get included, "
                . 'so any local user able to write here could execute code as the web server user.',
            suggestion: "Run 'chmod 0700 $directory', or point view.cache_directory at a private "
                . "directory such as 'storage/views' (relative paths resolve against the project root)",
        );
    }

    public static function notOwnedByCurrentUser(
        string $directory,
        int $ownerUid,
        int $currentUid,
    ): self {
        return new self(
            message: "Refusing to use compiled-template cache directory '$directory' owned by another user",
            context: "Directory is owned by uid $ownerUid but the current process runs as uid $currentUid. "
                . 'Compiled templates are PHP files that get included, so the owner of this directory '
                . 'could plant code that runs as the web server user.',
            suggestion: 'Point view.cache_directory at a directory owned by the web server user, '
                . "such as 'storage/views' (relative paths resolve against the project root)",
        );
    }
}

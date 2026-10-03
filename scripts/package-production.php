<?php

declare(strict_types=1);

final class ProductionPackageBuilder
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return array{archive: string, archive_bytes: int, entry_count: int, source_bytes: int, folders: array<string, int>}
     */
    public function build(string $archivePath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the production archive.');
        }

        if (! is_file($this->projectRoot.'/public/build/manifest.json')) {
            throw new RuntimeException('Run npm run build before creating the production archive.');
        }

        $workspaceBytes = $this->directorySize($this->projectRoot);

        $archiveDirectory = dirname($archivePath);
        if (! is_dir($archiveDirectory) && ! mkdir($archiveDirectory, 0775, true) && ! is_dir($archiveDirectory)) {
            throw new RuntimeException('The production archive directory could not be created.');
        }

        $archive = new ZipArchive();
        $opened = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('The production archive could not be opened for writing.');
        }

        $roots = [
            'artisan',
            '.env.example',
            'composer.json',
            'composer.lock',
            'README.md',
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'database/seeders',
            'public',
            'resources',
            'routes',
            'vendor',
        ];

        foreach ($roots as $relativePath) {
            $absolutePath = $this->projectRoot.'/'.$relativePath;
            if (! file_exists($absolutePath)) {
                throw new RuntimeException("Required production path is missing: {$relativePath}");
            }

            if (is_file($absolutePath)) {
                $this->addFile($archive, $absolutePath, $relativePath);
                continue;
            }

            $this->addDirectory($archive, $absolutePath, $relativePath);
        }

        foreach ([
            'bootstrap/cache',
            'storage/app/private',
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
        ] as $directory) {
            $archive->addEmptyDir($directory);
        }

        if (! $archive->close()) {
            throw new RuntimeException('The production archive could not be finalized.');
        }

        $folders = [];
        foreach (['app', 'bootstrap', 'config', 'database', 'node_modules', 'public', 'resources', 'routes', 'scripts', 'storage', 'tests', 'vendor', '.git'] as $folder) {
            $folders[$folder] = $this->directorySize($this->projectRoot.'/'.$folder);
        }

        return [
            'archive' => $archivePath,
            'archive_bytes' => (int) filesize($archivePath),
            'entry_count' => $this->archiveEntryCount($archivePath),
            'archive_uncompressed_bytes' => $this->archiveUncompressedSize($archivePath),
            'workspace_bytes_before_package' => $workspaceBytes,
            'folders' => $folders,
        ];
    }

    private function addDirectory(ZipArchive $archive, string $absolutePath, string $relativePath): void
    {
        $archive->addEmptyDir(str_replace('\\', '/', $relativePath));
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                continue;
            }

            $fullPath = $entry->getPathname();
            $entryRelativePath = str_replace('\\', '/', substr($fullPath, strlen($this->projectRoot) + 1));

            if ($this->isExcluded($entryRelativePath, $entry->isDir())) {
                continue;
            }

            if ($entry->isDir()) {
                $archive->addEmptyDir($entryRelativePath);
                continue;
            }

            $this->addFile($archive, $fullPath, $entryRelativePath);
        }
    }

    private function addFile(ZipArchive $archive, string $absolutePath, string $relativePath): void
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($this->isExcluded($relativePath, false)) {
            return;
        }

        if (! $archive->addFile($absolutePath, $relativePath)) {
            throw new RuntimeException("A production file could not be added: {$relativePath}");
        }

        $archive->setCompressionName($relativePath, ZipArchive::CM_DEFLATE, 6);
    }

    private function isExcluded(string $relativePath, bool $isDirectory): bool
    {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, './'));

        if ($relativePath === 'public/.htaccess' || $relativePath === 'public/hot') {
            return true;
        }

        if ($relativePath === 'bootstrap/cache' || str_starts_with($relativePath, 'bootstrap/cache/')) {
            return true;
        }

        if ($relativePath === 'database/database.sqlite' || str_ends_with($relativePath, '.sqlite') || str_ends_with($relativePath, '.sqlite-wal') || str_ends_with($relativePath, '.sqlite-shm')) {
            return true;
        }

        if ($relativePath === 'public/storage' || str_starts_with($relativePath, 'public/storage/')) {
            return true;
        }

        if (str_starts_with($relativePath, 'storage/')) {
            return true;
        }

        return $isDirectory && in_array($relativePath, ['node_modules', 'tests', '.git', 'dist'], true);
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            $relativePath = str_replace('\\', '/', substr($entry->getPathname(), strlen($this->projectRoot) + 1));
            if (str_starts_with($relativePath, 'dist/')) {
                continue;
            }

            if ($entry->isFile() && ! $entry->isLink()) {
                $bytes += $entry->getSize();
            }
        }

        return $bytes;
    }

    private function archiveEntryCount(string $archivePath): int
    {
        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException('The completed production archive could not be reopened.');
        }

        $count = $archive->numFiles;
        $archive->close();

        return $count;
    }

    private function archiveUncompressedSize(string $archivePath): int
    {
        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException('The completed production archive could not be reopened.');
        }

        $bytes = 0;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);
            if ($entry !== false) {
                $bytes += $entry['size'];
            }
        }
        $archive->close();

        return $bytes;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        $projectRoot = dirname(__DIR__);
        $archivePath = $projectRoot.'/dist/school-qr-production.zip';
        $report = (new ProductionPackageBuilder($projectRoot))->build($archivePath);

        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage().PHP_EOL);
        exit(1);
    }
}
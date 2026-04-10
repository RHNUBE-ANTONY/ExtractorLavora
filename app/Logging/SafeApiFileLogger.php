<?php

namespace App\Logging;

use AWT\FileLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class SafeApiFileLogger extends FileLogger
{
    /**
     * Read stored logs safely on PHP 8.5+ where trailing bytes can break unserialize.
     *
     * @return Collection<int, object>|array<int, object>
     */
    public function getLogs()
    {
        if (! File::isDirectory($this->path)) {
            return [];
        }

        $files = glob($this->path.'/*.*');
        $contentCollection = collect();

        foreach ($files as $file) {
            if (File::isDirectory($file)) {
                continue;
            }

            $content = rtrim((string) file_get_contents($file));

            if ($content === '') {
                continue;
            }

            $decoded = @unserialize($content, ['allowed_classes' => true]);

            if ($decoded === false && $content !== 'b:0;') {
                continue;
            }

            $contentCollection->add((object) $decoded);
        }

        return $contentCollection->sortByDesc('created_at');
    }
}

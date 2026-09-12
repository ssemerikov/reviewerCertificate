<?php
namespace PKP\file;

/** Filesystem boundary double; browser tests exercise the actual OJS manager. */
class FileManager {
    public function deleteByPath($path) { return is_file($path) ? unlink($path) : false; }
    public function setMode($path, $mask) {
        return chmod($path, $mask & ~(\Config::getVar('files', 'umask') ?: 0022));
    }
}

<?php
namespace APP\plugins\generic\reviewerCertificate\classes;

/** Resolve the connection already configured by OJS, including port/socket/TLS settings. */
class DatabaseConnection {
    public static function get() {
        if (class_exists('Illuminate\Support\Facades\DB')) {
            try { return \Illuminate\Support\Facades\DB::connection(); }
            catch (\Throwable $e) { /* OJS 3.3 does not bootstrap facades. */ }
        }
        if (class_exists('Illuminate\Database\Capsule\Manager')) {
            return \Illuminate\Database\Capsule\Manager::connection();
        }
        throw new \RuntimeException('OJS database connection unavailable');
    }
}

<?php
namespace APP\plugins\generic\reviewerCertificate\classes\migration;

/** Add compatible constraints without deleting pre-existing orphan records. */
class SchemaIntegrity {
    private static function column($db, $schema, $table, $name) {
        if (method_exists($schema, 'getColumns')) {
            foreach ($schema->getColumns($table) as $column) {
                if ($column['name'] === $name) {
                    return [strpos($column['type_name'], 'big') !== false ? 'bigInteger' : 'integer',
                        strpos($column['type'], 'unsigned') !== false, $column['nullable']];
                }
            }
        } else {
            $column = $db->getDoctrineColumn($table, $name);
            return [strpos($column->getType()->getName(), 'big') !== false ? 'bigInteger' : 'integer',
                $column->getUnsigned(), !$column->getNotnull()];
        }
        throw new \RuntimeException('Missing referenced column');
    }

    private static function foreignKeys($db, $schema, $table) {
        if (method_exists($schema, 'getForeignKeys')) {
            return array_column($schema->getForeignKeys($table), 'name');
        }
        $names = [];
        foreach ($db->getDoctrineSchemaManager()->listTableForeignKeys($table) as $key) { $names[] = $key->getName(); }
        return $names;
    }

    public static function apply($db) {
        $schema = $db->getSchemaBuilder();
        // Older DAO versions cast an absent optional template to zero. It is
        // not a template identity: restore SQL NULL without changing certificates.
        if (!$db->table('reviewer_certificate_templates')->where('template_id', 0)->exists()) {
            $db->table('reviewer_certificates')->where('template_id', 0)->update(['template_id' => null]);
        }
        $relations = [
            ['reviewer_certificate_templates', 'context_id', 'journals', 'journal_id', 'cascade', 'rc_template_context_fk'],
            ['reviewer_certificates', 'reviewer_id', 'users', 'user_id', 'cascade', 'rc_certificate_reviewer_fk'],
            ['reviewer_certificates', 'submission_id', 'submissions', 'submission_id', 'cascade', 'rc_certificate_submission_fk'],
            ['reviewer_certificates', 'review_id', 'review_assignments', 'review_id', 'cascade', 'rc_certificate_review_fk'],
            ['reviewer_certificates', 'context_id', 'journals', 'journal_id', 'cascade', 'rc_certificate_context_fk'],
            ['reviewer_certificates', 'template_id', 'reviewer_certificate_templates', 'template_id', 'set null', 'rc_certificate_template_fk'],
            ['reviewer_certificate_settings', 'template_id', 'reviewer_certificate_templates', 'template_id', 'cascade', 'rc_settings_template_fk'],
            ['reviewer_certificate_notifications', 'certificate_id', 'reviewer_certificates', 'certificate_id', 'cascade', 'rc_notification_certificate_fk'],
        ];
        foreach ($relations as $relation) {
            list($table, $column, $parent, $parentColumn, $delete, $name) = $relation;
            if (in_array($name, self::foreignKeys($db, $schema, $table), true)) { continue; }
            $orphans = $db->table($table . ' as child')->leftJoin($parent . ' as parent', 'child.' . $column, '=', 'parent.' . $parentColumn)
                ->whereNotNull('child.' . $column)->whereNull('parent.' . $parentColumn)->count();
            if ($orphans) {
                // A previous release may have orphan data. Preserve it and let
                // the administrator reconcile it before rerunning the upgrade.
                error_log('ReviewerCertificate: deferred constraint ' . $name . '; orphan rows: ' . $orphans);
                continue;
            }
            $source = self::column($db, $schema, $table, $column);
            $target = self::column($db, $schema, $parent, $parentColumn);
            if ($source[0] !== $target[0] || $source[1] !== $target[1]) {
                $schema->table($table, function ($blueprint) use ($column, $target, $source) {
                    $type = $target[0];
                    $blueprint->$type($column)->unsigned($target[1])->nullable($source[2])->change();
                });
            }
            $schema->table($table, function ($blueprint) use ($column, $parent, $parentColumn, $delete, $name) {
                $blueprint->foreign($column, $name)->references($parentColumn)->on($parent)->onDelete($delete);
            });
        }
    }
}

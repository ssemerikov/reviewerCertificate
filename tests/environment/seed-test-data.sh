#!/bin/bash
set -euo pipefail
# Seeds test data into each OJS instance for E2E testing
# Creates: journal, users, submissions, review assignments
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

COMPOSE="docker compose -f docker-compose.yml"

echo "=== Seeding Test Data ==="

# Generate bcrypt hash for 'testpass123' via PHP on host
PASS_HASH=$(php -r "echo password_hash('testpass123', PASSWORD_BCRYPT);")
echo "Password hash generated."

run_sql() {
    local db_name="$1"
    local sql="$2"
    $COMPOSE exec -T db mysql -uojs -pojs_test_pass -D "$db_name" -e "$sql" 2>&1
}

query_val() {
    local db_name="$1"
    local sql="$2"
    $COMPOSE exec -T db mysql -uojs -pojs_test_pass -D "$db_name" -N -e "$sql" 2>/dev/null | tr -d '[:space:]'
}

seed_instance() {
    local db_name="$1"
    local version_label="$2"

    echo ""
    echo "--- Seeding $version_label ($db_name) ---"

    # Check if already seeded (check for review assignments, not just journal)
    local ra_count
    ra_count=$(query_val "$db_name" "SELECT COUNT(*) FROM review_assignments;")
    if [ "$ra_count" != "0" ] && [ -n "$ra_count" ]; then
        echo "  Already seeded ($ra_count review assignments), skipping."
        return 0
    fi

    # Check if journal exists (may have been created via API)
    local journal_exists
    journal_exists=$(query_val "$db_name" "SELECT COUNT(*) FROM journals WHERE path='testjournal';")

    # Detect version-specific schema
    local pub_has_locale
    pub_has_locale=$(query_val "$db_name" \
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='publications' AND COLUMN_NAME='locale';")

    local ra_has_unconsidered
    ra_has_unconsidered=$(query_val "$db_name" \
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='review_assignments' AND COLUMN_NAME='unconsidered';")

    local users_date_last_login_nullable
    users_date_last_login_nullable=$(query_val "$db_name" \
        "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users' AND COLUMN_NAME='date_last_login';")

    echo "  Schema: pub_locale=$pub_has_locale, ra_unconsidered=$ra_has_unconsidered, date_last_login_nullable=$users_date_last_login_nullable"

    # Build version-specific user INSERT
    local date_login_col="" date_login_val=""
    if [ "$users_date_last_login_nullable" = "NO" ]; then
        date_login_col=", date_last_login"
        date_login_val=", NOW()"
    fi

    # === Step 1: Create users ===
    echo "  Creating users..."
    run_sql "$db_name" "
        INSERT IGNORE INTO users (username, password, email, date_registered, must_change_password ${date_login_col})
        VALUES ('testeditor', '${PASS_HASH}', 'editor-${db_name}@test.local', NOW(), 0 ${date_login_val});

        INSERT IGNORE INTO users (username, password, email, date_registered, must_change_password ${date_login_col})
        VALUES ('testreviewer', '${PASS_HASH}', 'reviewer-${db_name}@test.local', NOW(), 0 ${date_login_val});

        INSERT IGNORE INTO users (username, password, email, date_registered, must_change_password ${date_login_col})
        VALUES ('testauthor', '${PASS_HASH}', 'author-${db_name}@test.local', NOW(), 0 ${date_login_val});
    "

    # Set user names
    local editor_id reviewer_id author_id admin_id
    editor_id=$(query_val "$db_name" "SELECT user_id FROM users WHERE username='testeditor';")
    reviewer_id=$(query_val "$db_name" "SELECT user_id FROM users WHERE username='testreviewer';")
    author_id=$(query_val "$db_name" "SELECT user_id FROM users WHERE username='testauthor';")
    admin_id=$(query_val "$db_name" "SELECT user_id FROM users WHERE username='testadmin';")

    echo "  User IDs: admin=$admin_id, editor=$editor_id, reviewer=$reviewer_id, author=$author_id"

    # Set names in BOTH en and en_US locales for OJS 3.3 compatibility
    run_sql "$db_name" "
        INSERT IGNORE INTO user_settings (user_id, locale, setting_name, setting_value) VALUES
            ($editor_id, 'en', 'givenName', 'Test'), ($editor_id, 'en', 'familyName', 'Editor'),
            ($editor_id, 'en_US', 'givenName', 'Test'), ($editor_id, 'en_US', 'familyName', 'Editor'),
            ($reviewer_id, 'en', 'givenName', 'Test'), ($reviewer_id, 'en', 'familyName', 'Reviewer'),
            ($reviewer_id, 'en_US', 'givenName', 'Test'), ($reviewer_id, 'en_US', 'familyName', 'Reviewer'),
            ($author_id, 'en', 'givenName', 'Test'), ($author_id, 'en', 'familyName', 'Author'),
            ($author_id, 'en_US', 'givenName', 'Test'), ($author_id, 'en_US', 'familyName', 'Author');
    "

    # === Step 2: Create journal (if not already created via API) ===
    local journal_id
    journal_id=$(query_val "$db_name" "SELECT journal_id FROM journals WHERE path='testjournal';")
    if [ -z "$journal_id" ]; then
        echo "  Creating journal..."
        run_sql "$db_name" "
            INSERT INTO journals (path, seq, primary_locale, enabled)
            VALUES ('testjournal', 1, 'en', 1);
        "
        journal_id=$(query_val "$db_name" "SELECT journal_id FROM journals WHERE path='testjournal';")
    else
        echo "  Journal already exists (id=$journal_id)."
    fi
    echo "  Journal ID: $journal_id"

    run_sql "$db_name" "
        INSERT IGNORE INTO journal_settings (journal_id, locale, setting_name, setting_value) VALUES
            ($journal_id, 'en', 'name', 'Test Journal'),
            ($journal_id, 'en', 'acronym', 'TJ'),
            ($journal_id, 'en_US', 'name', 'Test Journal'),
            ($journal_id, 'en_US', 'acronym', 'TJ'),
            ($journal_id, '', 'supportedLocales', 'a:1:{i:0;s:2:\"en\";}'),
            ($journal_id, '', 'primaryLocale', 'en');
    "

    # === Step 3: Create user groups & assign roles ===
    echo "  Setting up roles..."

    # OJS role constants: Manager=16, Sub Editor (Section Editor)=17, Reviewer=4096, Author=65536
    for role_id in 16 17 4096 65536; do
        local existing
        existing=$(query_val "$db_name" "SELECT user_group_id FROM user_groups WHERE context_id=$journal_id AND role_id=$role_id LIMIT 1;")
        if [ -z "$existing" ]; then
            run_sql "$db_name" "INSERT INTO user_groups (context_id, role_id, is_default) VALUES ($journal_id, $role_id, 0);" > /dev/null
        fi
    done

    local mg_id se_id rv_id au_id
    mg_id=$(query_val "$db_name" "SELECT user_group_id FROM user_groups WHERE context_id=$journal_id AND role_id=16 LIMIT 1;")
    se_id=$(query_val "$db_name" "SELECT user_group_id FROM user_groups WHERE context_id=$journal_id AND role_id=17 LIMIT 1;")
    rv_id=$(query_val "$db_name" "SELECT user_group_id FROM user_groups WHERE context_id=$journal_id AND role_id=4096 LIMIT 1;")
    au_id=$(query_val "$db_name" "SELECT user_group_id FROM user_groups WHERE context_id=$journal_id AND role_id=65536 LIMIT 1;")

    run_sql "$db_name" "
        INSERT IGNORE INTO user_user_groups (user_group_id, user_id) VALUES
            ($mg_id, $admin_id),
            ($se_id, $editor_id),
            ($rv_id, $reviewer_id),
            ($au_id, $author_id);
    " > /dev/null

    # === Step 4: Create section (if not already exists) ===
    local section_id
    section_id=$(query_val "$db_name" "SELECT section_id FROM sections WHERE journal_id=$journal_id LIMIT 1;")
    if [ -z "$section_id" ]; then
        echo "  Creating section..."
        run_sql "$db_name" "
            INSERT INTO sections (journal_id, seq, editor_restricted) VALUES ($journal_id, 1, 0);
        " > /dev/null
        section_id=$(query_val "$db_name" "SELECT section_id FROM sections WHERE journal_id=$journal_id LIMIT 1;")
    else
        echo "  Section already exists (id=$section_id)."
    fi

    run_sql "$db_name" "
        INSERT IGNORE INTO section_settings (section_id, locale, setting_name, setting_value) VALUES
            ($section_id, 'en', 'title', 'Articles'),
            ($section_id, 'en', 'abbrev', 'ART');
    " > /dev/null

    # === Step 5: Create submissions + publications + review assignments ===
    echo "  Creating submissions..."

    # Build version-specific publication INSERT
    local pub_cols="submission_id, status, seq, version, section_id"
    local pub_vals_tpl="@sub_id, 1, 0, 1, $section_id"
    if [ -n "$pub_has_locale" ]; then
        pub_cols="submission_id, status, seq, version, locale, section_id"
        pub_vals_tpl="@sub_id, 1, 0, 1, 'en', $section_id"
    fi

    # Build version-specific review assignment columns
    local ra_extra_col="unconsidered"
    local ra_extra_val="0"
    if [ -z "$ra_has_unconsidered" ]; then
        ra_extra_col="considered, request_resent"
        ra_extra_val="NULL, 0"
    fi

    # --- Submission 1: Completed review (for certificate download) ---
    run_sql "$db_name" "
        INSERT INTO submissions (context_id, date_submitted, last_modified, status, locale, stage_id)
        VALUES ($journal_id, DATE_SUB(NOW(), INTERVAL 30 DAY), NOW(), 1, 'en', 3);
        SET @sub_id = LAST_INSERT_ID();

        INSERT INTO publications ($pub_cols)
        VALUES ($pub_vals_tpl);
        SET @pub_id = LAST_INSERT_ID();
        UPDATE submissions SET current_publication_id=@pub_id WHERE submission_id=@sub_id;

        INSERT INTO publication_settings (publication_id, locale, setting_name, setting_value)
        VALUES (@pub_id, 'en', 'title', 'Effects of Climate Change on Coral Reef Biodiversity');

        INSERT IGNORE INTO publication_settings (publication_id, locale, setting_name, setting_value)
        VALUES (@pub_id, 'en_US', 'title', 'Effects of Climate Change on Coral Reef Biodiversity');

        INSERT INTO review_rounds (submission_id, stage_id, round, status)
        VALUES (@sub_id, 3, 1, 1);
        SET @rr_id = LAST_INSERT_ID();

        INSERT INTO review_assignments (
            submission_id, reviewer_id, date_assigned, date_notified,
            date_confirmed, date_completed, date_due, date_response_due,
            round, stage_id, review_round_id, recommendation,
            declined, cancelled, $ra_extra_col
        ) VALUES (
            @sub_id, $reviewer_id, DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY),
            DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY),
            DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY),
            1, 3, @rr_id, 2,
            0, 0, $ra_extra_val
        );
    "

    # --- Submission 2: In-progress review (not eligible for certificate) ---
    run_sql "$db_name" "
        INSERT INTO submissions (context_id, date_submitted, last_modified, status, locale, stage_id)
        VALUES ($journal_id, DATE_SUB(NOW(), INTERVAL 15 DAY), NOW(), 1, 'en', 3);
        SET @sub_id = LAST_INSERT_ID();

        INSERT INTO publications ($pub_cols)
        VALUES ($pub_vals_tpl);
        SET @pub_id = LAST_INSERT_ID();
        UPDATE submissions SET current_publication_id=@pub_id WHERE submission_id=@sub_id;

        INSERT INTO publication_settings (publication_id, locale, setting_name, setting_value)
        VALUES (@pub_id, 'en', 'title', 'Machine Learning Approaches in Medical Diagnostics');

        INSERT IGNORE INTO publication_settings (publication_id, locale, setting_name, setting_value)
        VALUES (@pub_id, 'en_US', 'title', 'Machine Learning Approaches in Medical Diagnostics');

        INSERT INTO review_rounds (submission_id, stage_id, round, status)
        VALUES (@sub_id, 3, 1, 1);
        SET @rr_id = LAST_INSERT_ID();

        INSERT INTO review_assignments (
            submission_id, reviewer_id, date_assigned, date_notified,
            date_confirmed, date_completed, date_due, date_response_due,
            round, stage_id, review_round_id, recommendation,
            declined, cancelled, $ra_extra_col
        ) VALUES (
            @sub_id, $reviewer_id, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY),
            DATE_SUB(NOW(), INTERVAL 8 DAY), NULL,
            DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY),
            1, 3, @rr_id, NULL,
            0, 0, $ra_extra_val
        );
    "

    # --- Submission 3: Another completed review (for batch testing) ---
    run_sql "$db_name" "
        INSERT INTO submissions (context_id, date_submitted, last_modified, status, locale, stage_id)
        VALUES ($journal_id, DATE_SUB(NOW(), INTERVAL 45 DAY), NOW(), 1, 'en', 3);
        SET @sub_id = LAST_INSERT_ID();

        INSERT INTO publications ($pub_cols)
        VALUES ($pub_vals_tpl);
        SET @pub_id = LAST_INSERT_ID();
        UPDATE submissions SET current_publication_id=@pub_id WHERE submission_id=@sub_id;

        INSERT INTO publication_settings (publication_id, locale, setting_name, setting_value)
        VALUES (@pub_id, 'en', 'title', 'Quantum Computing and Cryptographic Security');

        INSERT IGNORE INTO publication_settings (publication_id, locale, setting_name, setting_value)
        VALUES (@pub_id, 'en_US', 'title', 'Quantum Computing and Cryptographic Security');

        INSERT INTO review_rounds (submission_id, stage_id, round, status)
        VALUES (@sub_id, 3, 1, 1);
        SET @rr_id = LAST_INSERT_ID();

        INSERT INTO review_assignments (
            submission_id, reviewer_id, date_assigned, date_notified,
            date_confirmed, date_completed, date_due, date_response_due,
            round, stage_id, review_round_id, recommendation,
            declined, cancelled, $ra_extra_col
        ) VALUES (
            @sub_id, $reviewer_id, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY),
            DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY),
            DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY),
            1, 3, @rr_id, 2,
            0, 0, $ra_extra_val
        );
    "

    echo "  $version_label seeded successfully."
}

seed_instance "ojs33" "OJS 3.3"
seed_instance "ojs34" "OJS 3.4"
seed_instance "ojs35" "OJS 3.5"

echo ""
echo "=== Seeding Complete ==="
echo ""
echo "Test accounts (all instances):"
echo "  Admin:    testadmin / testpass123"
echo "  Editor:   testeditor / testpass123"
echo "  Reviewer: testreviewer / testpass123"
echo "  Author:   testauthor / testpass123"
echo ""
echo "Test data:"
echo "  Submission 1: 'Effects of Climate Change...' - review COMPLETED"
echo "  Submission 2: 'Machine Learning...' - review IN PROGRESS"
echo "  Submission 3: 'Quantum Computing...' - review COMPLETED"

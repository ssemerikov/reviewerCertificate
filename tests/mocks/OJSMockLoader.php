<?php
/**
 * OJS Mock Loader
 *
 * Loads OJS core classes and creates necessary mocks for testing
 * Handles version-specific differences between OJS 3.3, 3.4, and 3.5
 */

class OJSMockLoader
{
    /** Insert ID handed back by the mocked base DAO. */
    public const MOCK_INSERT_ID = 424242;

    /** Nesting depth at which the mocked OJS 3.4 shim gives up and reports recursion. */
    public const MAX_INSERT_ID_DEPTH = 200;

    /** @var string Current OJS version */
    private static $version;

    /** @var bool Whether mocks have been initialized */
    private static $initialized = false;

    /**
     * Initialize OJS mocks for the specified version
     *
     * @param string $version OJS version (3.3, 3.4, or 3.5)
     */
    public static function initialize(string $version = '3.4'): void
    {
        if (self::$initialized) {
            return;
        }

        self::$version = $version;

        // Define OJS constants
        self::defineConstants();

        // Define OJS global functions
        self::defineGlobalFunctions();

        // Load base classes
        self::loadBaseClasses();

        // Load version-specific mocks
        self::loadVersionSpecificMocks();

        self::$initialized = true;
    }

    /**
     * Define OJS constants required by the plugin
     */
    private static function defineConstants(): void
    {
        if (!defined('ASSOC_TYPE_REVIEW_ASSIGNMENT')) {
            define('ASSOC_TYPE_REVIEW_ASSIGNMENT', 0x0000203);
        }

        if (!defined('REVIEW_ASSIGNMENT_STATUS_COMPLETE')) {
            define('REVIEW_ASSIGNMENT_STATUS_COMPLETE', 7);
        }

        if (!defined('ROLE_ID_REVIEWER')) {
            define('ROLE_ID_REVIEWER', 0x00001000);
        }

        if (!defined('ROLE_ID_MANAGER')) {
            define('ROLE_ID_MANAGER', 0x00000010);
        }

        if (!defined('ROLE_ID_SITE_ADMIN')) {
            define('ROLE_ID_SITE_ADMIN', 0x00000001);
        }

        if (!defined('HAS_REVIEW')) {
            define('HAS_REVIEW', true);
        }
    }

    /**
     * Define OJS global functions
     */
    private static function defineGlobalFunctions(): void
    {
        // import() function - OJS uses this to load class files
        if (!function_exists('import')) {
            function import($classPath) {
                // Mock implementation - in real OJS, this loads class files
                // For testing, we rely on the autoloader in bootstrap.php
                return true;
            }
        }

        // __() function - OJS translation function
        if (!function_exists('__')) {
            function __($key, $params = [], $locale = null) {
                // Mock translation - just return the key
                return $key;
            }
        }
    }

    /**
     * Load OJS base classes (or create mocks)
     */
    private static function loadBaseClasses(): void
    {
        // Create namespaced PKP classes for OJS 3.4+/3.5
        if (!class_exists('PKP\core\Core')) {
            eval('
                namespace PKP\core;
                class Core {
                    public static function getCurrentDate() {
                        return date("Y-m-d H:i:s");
                    }

                    public static function getBaseDir() {
                        return BASE_SYS_DIR;
                    }
                }
            ');
        }

        // Create namespaced DataObject for OJS 3.4+/3.5
        if (!class_exists('PKP\core\DataObject')) {
            eval('
                namespace PKP\core;
                class DataObject {
                    private $_data = [];

                    public function setData($key, $value) {
                        $this->_data[$key] = $value;
                    }

                    public function getData($key) {
                        return $this->_data[$key] ?? null;
                    }

                    public function setAllData($data) {
                        $this->_data = $data;
                    }

                    public function getAllData() {
                        return $this->_data;
                    }
                }
            ');
        }

        // Create namespaced DAO base class for OJS 3.4+/3.5.
        // Modelled on the REAL pkp-lib class shape per version -- see defineBaseDAO().
        if (!class_exists('PKP\db\DAO')) {
            self::defineBaseDAO();
        }

        // Create namespaced DAOResultFactory for OJS 3.4+/3.5
        if (!class_exists('PKP\db\DAOResultFactory')) {
            eval('
                namespace PKP\db;
                class DAOResultFactory extends \ArrayIterator {
                    public function __construct($result, $dao, $method) {
                        parent::__construct([]);
                    }
                }
            ');
        }

        // Create namespaced DAORegistry for OJS 3.4+/3.5
        if (!class_exists('PKP\db\DAORegistry')) {
            eval('
                namespace PKP\db;
                class DAORegistry {
                    private static $daos = [];

                    public static function getDAO($name) {
                        return self::$daos[$name] ?? null;
                    }

                    public static function registerDAO($name, $dao) {
                        self::$daos[$name] = $dao;
                    }
                }
            ');
        }

        // Create namespaced GenericPlugin for OJS 3.4+/3.5
        if (!class_exists('PKP\plugins\GenericPlugin')) {
            eval('
                namespace PKP\plugins;
                class GenericPlugin {
                    private $_pluginSettings = [];
                    private $_enabled = true;

                    public function register($category, $path, $mainContextId = null) {
                        return true;
                    }

                    public function getEnabled($contextId = null) {
                        return $this->_enabled;
                    }

                    public function setEnabled($enabled) {
                        $this->_enabled = $enabled;
                    }

                    public function getSetting($contextId, $name) {
                        return $this->_pluginSettings[$contextId][$name] ?? null;
                    }

                    public function updateSetting($contextId, $name, $value) {
                        $this->_pluginSettings[$contextId][$name] = $value;
                    }

                    public function getPluginPath() {
                        return BASE_SYS_DIR;
                    }

                    public function getTemplatePath() {
                        return BASE_SYS_DIR . "/templates/";
                    }

                    public function getTemplateResource($template) {
                        return BASE_SYS_DIR . "/templates/" . $template;
                    }

                    public function getCanEnable() {
                        return true;
                    }

                    public function getCanDisable() {
                        return true;
                    }
                }
            ');
        }

        // Create namespaced Hook for OJS 3.4+/3.5
        if (!class_exists('PKP\plugins\Hook')) {
            eval('
                namespace PKP\plugins;
                class Hook {
                    private static $hooks = [];

                    public static function register($hook, $callback, $priority = 0) {
                        self::$hooks[$hook][] = $callback;
                        return true;
                    }

                    public static function call($hook, $args = []) {
                        if (isset(self::$hooks[$hook])) {
                            foreach (self::$hooks[$hook] as $callback) {
                                call_user_func_array($callback, $args);
                            }
                        }
                    }
                }
            ');
        }

        // Create namespaced Form classes for OJS 3.4+/3.5
        if (!class_exists('PKP\form\Form')) {
            eval('
                namespace PKP\form;
                class Form {
                    private $_data = [];
                    protected $_template;
                    protected $_checks = [];

                    public function __construct($template = null) {
                        $this->_template = $template;
                    }

                    public function setData($key, $value = null) {
                        if (is_array($key)) {
                            $this->_data = array_merge($this->_data, $key);
                        } else {
                            $this->_data[$key] = $value;
                        }
                    }

                    public function getData($key = null) {
                        if ($key === null) return $this->_data;
                        return $this->_data[$key] ?? null;
                    }

                    /**
                     * Values readUserVars() should hand back, keyed by field name.
                     * Stands in for the request in tests.
                     */
                    public static $mockUserVars = [];

                    public function readInputData() {}

                    public function readUserVars($vars) {
                        foreach ($vars as $name) {
                            $value = self::$mockUserVars[$name] ?? null;
                            // OJS pipes every user var through Core::cleanVar(), which
                            // trims. That trim is why leading blank lines in the body
                            // template never survive a save (Issue #74).
                            $this->setData($name, is_string($value) ? trim($value) : $value);
                        }
                    }
                    public function validate() { return true; }
                    public function execute() { return true; }
                    public function fetch($request, $template = null, $display = false) { return ""; }
                    public function addCheck($check) { $this->_checks[] = $check; }

                    /** Registered validators, so tests can assert which fields are required. */
                    public function getMockChecks() { return $this->_checks; }
                    public function addError($field, $message) {}
                }
            ');
        }

        // Form validators
        if (!class_exists('PKP\form\validation\FormValidatorPost')) {
            eval('
                namespace PKP\form\validation;
                class FormValidatorPost { public function __construct($form) {} }
                class FormValidatorCSRF { public function __construct($form) {} }
                class FormValidator {
                    public $mockField;
                    public $mockType;
                    public function __construct($form, $field = "", $type = "", $msg = "") {
                        $this->mockField = $field;
                        $this->mockType = $type;
                    }
                }
                class FormValidatorCustom extends FormValidator {
                    public function __construct($form, $field = "", $type = "", $msg = "", $callback = null) {
                        parent::__construct($form, $field, $type, $msg);
                    }
                }
            ');
        }

        // Create namespaced Handler for OJS 3.4+/3.5
        if (!class_exists('APP\handler\Handler')) {
            eval('
                namespace APP\handler;
                class Handler {
                    protected $_roleAssignments = [];

                    public function __construct() {}

                    public function addRoleAssignment($roles, $operations) {
                        foreach ((array)$roles as $role) {
                            $this->_roleAssignments[$role] = $operations;
                        }
                    }

                    public function authorize($request, &$args, $roleAssignments) {
                        return true;
                    }

                    public function addPolicy($policy) {}
                }
            ');
        }

        // Create namespaced Role and auth classes for OJS 3.4+/3.5
        if (!class_exists('PKP\security\Role')) {
            eval('
                namespace PKP\security;
                class Role {
                    const ROLE_ID_REVIEWER = 0x00001000;
                    const ROLE_ID_MANAGER = 0x00000010;
                    const ROLE_ID_SITE_ADMIN = 0x00000001;
                }
            ');
        }

        if (!class_exists('PKP\security\authorization\ContextAccessPolicy')) {
            eval('
                namespace PKP\security\authorization;
                class ContextAccessPolicy {
                    public function __construct($request, $roleAssignments) {}
                }
            ');
        }

        if (!class_exists('PKP\core\JSONMessage')) {
            eval('
                namespace PKP\core;
                class JSONMessage {
                    public function __construct($success = true, $content = null) {}
                    public function getString() { return "{}"; }
                }
            ');
        }

        // Create Core class (legacy - global namespace)
        if (!class_exists('Core')) {
            eval('
                class Core {
                    public static function getCurrentDate() {
                        return date("Y-m-d H:i:s");
                    }

                    public static function getBaseDir() {
                        return BASE_SYS_DIR;
                    }
                }
            ');
        }

        // Create DataObject base class
        if (!class_exists('DataObject')) {
            eval('
                class DataObject {
                    private $_data = [];

                    public function setData($key, $value) {
                        $this->_data[$key] = $value;
                    }

                    public function getData($key) {
                        return $this->_data[$key] ?? null;
                    }

                    public function setAllData($data) {
                        $this->_data = $data;
                    }

                    public function getAllData() {
                        return $this->_data;
                    }
                }
            ');
        }

        // Create GenericPlugin base class
        if (!class_exists('GenericPlugin')) {
            eval('
                class GenericPlugin {
                    private $_pluginSettings = [];
                    private $_enabled = true;

                    public function register($category, $path, $mainContextId = null) {
                        return true;
                    }

                    public function getEnabled($contextId = null) {
                        return $this->_enabled;
                    }

                    public function setEnabled($enabled) {
                        $this->_enabled = $enabled;
                    }

                    public function getSetting($contextId, $name) {
                        return $this->_pluginSettings[$contextId][$name] ?? null;
                    }

                    public function updateSetting($contextId, $name, $value) {
                        $this->_pluginSettings[$contextId][$name] = $value;
                    }

                    public function getPluginPath() {
                        return BASE_SYS_DIR;
                    }

                    public function getTemplatePath() {
                        return BASE_SYS_DIR . \'/templates/\';
                    }

                    public function getCanEnable() {
                        return true;
                    }

                    public function getCanDisable() {
                        return true;
                    }

                    public function import($classPath) {
                        // Mock import - rely on autoloader
                        return true;
                    }
                }
            ');
        }

        // Create DAO base class
        if (!class_exists('DAO')) {
            eval('
                class DAO {
                    protected function _getInsertId($tableName = null, $idField = null) {
                        return rand(1, 999999);
                    }
                }
            ');
        }

        // Create Form base class
        if (!class_exists('Form')) {
            eval('
                class Form {
                    private $_data = [];
                    protected $_template;

                    public function __construct($template = null) {
                        $this->_template = $template;
                    }

                    public function setData($key, $value) {
                        $this->_data[$key] = $value;
                    }

                    public function getData($key) {
                        return $this->_data[$key] ?? null;
                    }

                    public function readInputData() {}

                    public function readUserVars($vars) {}

                    public function validate() {
                        return true;
                    }

                    public function execute() {}

                    public function addCheck($check) {}

                    public function addError($field, $message) {}
                }
            ');
        }

        // Create Handler base class
        if (!class_exists('Handler')) {
            eval('
                class Handler {
                    public function authorize($request, &$args, $roleAssignments) {
                        return true;
                    }
                }
            ');
        }

        // Create TemplateManager mock
        if (!class_exists('TemplateManager')) {
            eval('
                class TemplateManager {
                    private static $instance;
                    private $templateVars = [];

                    public static function getManager($request = null) {
                        if (!self::$instance) {
                            self::$instance = new self();
                        }
                        return self::$instance;
                    }

                    public function assign($key, $value) {
                        $this->templateVars[$key] = $value;
                    }

                    public function fetch($template) {
                        return "<html>Mock Template: $template</html>";
                    }

                    public function display($template) {
                        echo $this->fetch($template);
                    }
                }
            ');
        }

        // Create DAORegistry mock
        if (!class_exists('DAORegistry')) {
            eval('
                class DAORegistry {
                    private static $daos = [];

                    public static function getDAO($name) {
                        return self::$daos[$name] ?? null;
                    }

                    public static function registerDAO($name, $dao) {
                        self::$daos[$name] = $dao;
                    }
                }
            ');
        }

        // Create HookRegistry mock
        if (!class_exists('HookRegistry')) {
            eval('
                class HookRegistry {
                    private static $hooks = [];

                    public static function register($hook, $callback, $priority = 0) {
                        self::$hooks[$hook][] = $callback;
                        return true;
                    }

                    public static function call($hook, $args = []) {
                        if (isset(self::$hooks[$hook])) {
                            foreach (self::$hooks[$hook] as $callback) {
                                call_user_func_array($callback, $args);
                            }
                        }
                    }
                }
            ');
        }

        // Create PKP mail mocks (OJS 3.4+/3.5 Mailable system)
        // eval() is safe here: test-only code defining classes from static strings
        if (!trait_exists('PKP\mail\traits\Configurable')) {
            eval('
                namespace PKP\mail\traits;
                trait Configurable {}
                trait Sender {
                    public $mockSender;
                    public function sender($sender, $defaultLocale = null) {
                        $this->mockSender = $sender;
                        return $this;
                    }
                }
            ');
        }
        if (!class_exists('PKP\mail\Mailable')) {
            eval('
                namespace PKP\mail;
                class Mailable {
                    protected static ?string $name = null;
                    protected static ?string $description = null;
                    protected static ?string $emailTemplateKey = null;
                    public $mockFrom = null;
                    public $mockTo = [];
                    public $mockSubject = null;
                    public $mockBody = null;
                    public $mockAttachments = [];
                    public function __construct($variables = []) {}
                    public static function getEmailTemplateKey() { return static::$emailTemplateKey; }
                    public function from($address, $name = null) { $this->mockFrom = [$address, $name]; return $this; }
                    public function to($address, $name = null) { $this->mockTo[] = [$address, $name]; return $this; }
                    public function subject($subject) { $this->mockSubject = $subject; return $this; }
                    public function body($body) { $this->mockBody = $body; return $this; }
                    public function attachData($data, $name, $options = []) { $this->mockAttachments[] = [$name, strlen($data), $options]; return $this; }
                }
            ');
        }

        // Create Illuminate mail-layer mocks modelling pkp-lib 3.4+ behavior:
        // Mailer::sendSymfonyMessage() swallows TransportException, so the
        // ONLY observable success signal is the MessageSent event — it fires
        // when the transport accepted the message and stays silent otherwise.
        // eval() is safe here: test-only code defining classes from static
        // strings, conditionally (see file header).
        if (!class_exists('Illuminate\Mail\Events\MessageSent')) {
            eval('
                namespace Illuminate\Mail\Events;
                class MessageSent {}
            ');
        }
        if (!class_exists('Illuminate\Support\Facades\Event')) {
            eval('
                namespace Illuminate\Support\Facades;
                class Event {
                    public static $listeners = [];
                    public static function listen($event, $callback) {
                        self::$listeners[$event][] = $callback;
                    }
                    public static function mockFire($event) {
                        foreach (self::$listeners[$event] ?? [] as $callback) {
                            $callback(new $event());
                        }
                    }
                    public static function mockReset() {
                        self::$listeners = [];
                    }
                }
            ');
        }
        if (!class_exists('Illuminate\Support\Facades\Mail')) {
            eval('
                namespace Illuminate\Support\Facades;
                class Mail {
                    /** Transport accepts every message */
                    public static $transportAccepts = true;
                    /** Transport rejects messages carrying attachments (552-style size limit) */
                    public static $rejectWithAttachments = false;
                    public static $sent = [];
                    public static function send($mailable) {
                        self::$sent[] = $mailable;
                        $hasAttachments = !empty($mailable->mockAttachments);
                        if (self::$transportAccepts && !(self::$rejectWithAttachments && $hasAttachments)) {
                            Event::mockFire(\'Illuminate\\\\Mail\\\\Events\\\\MessageSent\');
                        }
                        // Rejected: pkp-lib swallows the TransportException —
                        // nothing observable happens.
                    }
                    public static function mockReset() {
                        self::$transportAccepts = true;
                        self::$rejectWithAttachments = false;
                        self::$sent = [];
                    }
                }
            ');
        }

        // Create Config mock (supports per-test overrides via setMockVar/clearMockVars)
        // eval() is safe here: test-only code defining a class from a static string,
        // required because the class must be defined conditionally (see file header)
        if (!class_exists('Config')) {
            eval('
                class Config {
                    public static $mockVars = [];

                    public static function getVar($section, $key, $default = null) {
                        if (isset(self::$mockVars[$section][$key])) {
                            return self::$mockVars[$section][$key];
                        }
                        if ($section === "database" && $key === "driver") {
                            return "mysqli";
                        }
                        return $default;
                    }

                    public static function setMockVar($section, $key, $value) {
                        self::$mockVars[$section][$key] = $value;
                    }

                    public static function clearMockVars() {
                        self::$mockVars = [];
                    }
                }
            ');
        }

        // Create Application mock
        if (!class_exists('Application')) {
            eval('
                class Application {
                    public static function get() {
                        return new self();
                    }

                    public function getRequest() {
                        return new PKPRequest();
                    }
                }
            ');
        }

        // Create PKPRequest mock
        if (!class_exists('PKPRequest')) {
            eval('
                class PKPRequest {
                    public function getContext() {
                        return null;
                    }

                    public function getUser() {
                        return null;
                    }
                }
            ');
        }

        // Create JSONMessage mock
        if (!class_exists('JSONMessage')) {
            eval('
                class JSONMessage {
                    private $content;

                    public function __construct($status = true, $content = null) {
                        $this->content = $content;
                    }

                    public function getString() {
                        return json_encode($this->content);
                    }
                }
            ');
        }
    }

    /**
     * Load version-specific mocks based on OJS version
     */
    /**
     * Define the PKP\db\DAO base class, mirroring the real pkp-lib class shape
     * for the OJS version under test.
     *
     * This fidelity matters. pkp-lib 3.4 ships a deprecated _getInsertId() that
     * simply forwards to getInsertId(), so any subclass overriding getInsertId()
     * recurses until the stack is exhausted. A benign catch-all mock hides that
     * failure mode completely -- which is exactly how it reached production.
     *
     *   OJS 3.3 (classes/db/DAO.inc.php:164)
     *       protected function _getInsertId()            <- real implementation, no args
     *
     *   OJS 3.4 (classes/db/DAO.php:201,211)
     *       protected function getInsertId(): int        <- real implementation
     *       public function _getInsertId(): int          <- deprecated shim, calls getInsertId()
     *
     *   OJS 3.5 (classes/db/DAO.php:179)
     *       protected function getInsertId(): int        <- _getInsertId() removed entirely
     *
     * The 3.4 shim carries a re-entrancy depth guard so a regression surfaces as a
     * clean assertion failure rather than exhausting PHP's stack and killing the
     * whole test run.
     */
    /**
     * Define the Laravel DB facade the way each OJS version presents it.
     *
     * OJS 3.4 / 3.5 -- bootstrapped; DB::getPdo()->lastInsertId() is the real
     *                  mechanism core itself uses.
     * OJS 3.3       -- pkp-lib 3.3.0-20+ ships the Laravel classes, but the DB is
     *                  NOT bootstrapped, so getPdo() throws. Code must fall through
     *                  to the base DAO instead of assuming the facade works.
     *
     * Tests can steer this via the public statics, then call mockReset().
     */
    private static function defineDbFacade(): void
    {
        if (!class_exists('ReviewerCertificateMockPdo')) {
            eval('
                class ReviewerCertificateMockPdo {
                    public function lastInsertId($name = null) {
                        return \Illuminate\Support\Facades\DB::$mockLastInsertId;
                    }
                }
            ');
        }

        if (!class_exists('Illuminate\Support\Facades\DB')) {
            eval('
                namespace Illuminate\Support\Facades;
                class DB {
                    /** @var bool false models OJS 3.3: classes present, DB not bootstrapped */
                    public static $mockBootstrapped = true;

                    /** @var int|string value handed back by lastInsertId() */
                    public static $mockLastInsertId = ' . self::MOCK_INSERT_ID . ';

                    public static function getPdo() {
                        if (!self::$mockBootstrapped) {
                            throw new \RuntimeException("A facade root has not been set.");
                        }
                        return new \ReviewerCertificateMockPdo();
                    }

                    public static function mockReset() {
                        self::$mockLastInsertId = ' . self::MOCK_INSERT_ID . ';
                    }
                }
            ');
        }

        // OJS 3.3 has the classes but no bootstrapped connection behind them.
        \Illuminate\Support\Facades\DB::$mockBootstrapped = version_compare(self::$version, '3.4', '>=');
    }

    private static function defineBaseDAO(): void
    {
        self::defineDbFacade();

        // Shared members every version needs. Single-quoted below, so the $vars
        // inside are literal source text rather than interpolated here.
        $common = '
            public function retrieve($sql, $params = []) {
                return new \ArrayIterator([]);
            }

            public function update($sql, $params = []) {
                return true;
            }
        ';

        if (version_compare(self::$version, '3.4', '<')) {
            // OJS 3.3 -- real _getInsertId(), and no getInsertId() to override
            eval('
                namespace PKP\db;
                class DAO {
                    /** Tests set this to 0 to model a driver that reports no insert ID. */
                    public static $mockInsertId = ' . self::MOCK_INSERT_ID . ';

                    protected function _getInsertId() {
                        return self::$mockInsertId;
                    }
                    ' . $common . '
                }
            ');
            return;
        }

        if (version_compare(self::$version, '3.5', '>=')) {
            // OJS 3.5 -- _getInsertId() was removed
            eval('
                namespace PKP\db;
                class DAO {
                    /** Tests set this to 0 to model a driver that reports no insert ID. */
                    public static $mockInsertId = ' . self::MOCK_INSERT_ID . ';

                    protected function getInsertId(): int {
                        return self::$mockInsertId;
                    }
                    ' . $common . '
                }
            ');
            return;
        }

        // OJS 3.4 -- the deprecated shim forwards straight back into getInsertId().
        //
        // The depth guard lives in _getInsertId(), not getInsertId(): a subclass that
        // overrides getInsertId() means the parent's body never runs, so a counter
        // there would never see the loop. The shim is the one frame guaranteed to be
        // on the cycle.
        eval('
            namespace PKP\db;
            class DAO {
                public static $insertIdDepth = 0;

                /** Tests set this to 0 to model a driver that reports no insert ID. */
                public static $mockInsertId = ' . self::MOCK_INSERT_ID . ';

                protected function getInsertId(): int {
                    return self::$mockInsertId;
                }

                public function _getInsertId(): int {
                    self::$insertIdDepth++;
                    try {
                        if (self::$insertIdDepth > ' . self::MAX_INSERT_ID_DEPTH . ') {
                            throw new \RuntimeException(
                                "Infinite recursion between the deprecated _getInsertId() shim "
                                . "and an overridden getInsertId() (nesting depth exceeded "
                                . ' . self::MAX_INSERT_ID_DEPTH . ' . ")"
                            );
                        }
                        return $this->getInsertId();
                    } finally {
                        self::$insertIdDepth--;
                    }
                }
                ' . $common . '
            }
        ');
    }

    private static function loadVersionSpecificMocks(): void
    {
        $version = self::$version;

        // OJS 3.4+ uses Repo facade pattern
        if (version_compare($version, '3.4', '>=')) {
            self::loadRepoFacade();
        }

        // OJS 3.3 uses traditional DAOs
        if (version_compare($version, '3.3', '>=') && version_compare($version, '3.4', '<')) {
            self::loadTraditionalDAOs();
        }

        // OJS 3.5 may have additional changes
        if (version_compare($version, '3.5', '>=')) {
            self::loadOJS35Specific();
        }
    }

    /**
     * Load OJS 3.4+ Repo facade pattern mocks
     */
    private static function loadRepoFacade(): void
    {
        if (!class_exists('APP\\facades\\Repo')) {
            eval('
                namespace APP\\facades {
                    class Repo {
                        public static function user() {
                            return new \\UserRepository();
                        }

                        public static function submission() {
                            return new \\SubmissionRepository();
                        }
                    }
                }
            ');
        }

        if (!class_exists('UserRepository')) {
            eval('
                class UserRepository {
                    public function get($userId) {
                        return null;
                    }
                }
            ');
        }

        if (!class_exists('SubmissionRepository')) {
            eval('
                class SubmissionRepository {
                    public function get($submissionId) {
                        return null;
                    }
                }
            ');
        }
    }

    /**
     * Load traditional DAO mocks for OJS 3.3
     */
    private static function loadTraditionalDAOs(): void
    {
        // In OJS 3.3, UserDAO and SubmissionDAO are used directly
        // These would be registered via DAORegistry
    }

    /**
     * Load OJS 3.5 specific mocks
     */
    private static function loadOJS35Specific(): void
    {
        // Placeholder for OJS 3.5 specific changes
        // Can be updated when 3.5 is released
    }

    /**
     * Get current OJS version
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return self::$version;
    }
}

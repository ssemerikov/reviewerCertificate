<?php
/**
 * OJS Mock Loader
 *
 * Loads OJS core classes and creates necessary mocks for testing
 * Handles version-specific differences between OJS 3.3, 3.4, and 3.5
 */

class OJSMockLoader
{
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
     * Load OJS base classes (or create mocks)
     */
    private static function loadBaseClasses(): void
    {
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

                    public function validate() {
                        return true;
                    }

                    public function execute() {}
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

        // Create Config mock
        if (!class_exists('Config')) {
            eval('
                class Config {
                    public static function getVar($section, $key, $default = null) {
                        if ($section === "database" && $key === "driver") {
                            return "mysqli";
                        }
                        return $default;
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

<?php
/**
 * Moodle Manager Class File
 *
 * PHP version 5
 *
 * @author    Tyler Bannister <tyler.bannister@remote-learner.net>
 * @copyright 2012-2015 Remote-Learner, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link      http://git.remote-learner.net/private.cgi?p=rlscripts.git
 */

/**
 * MOODLE_THEME_DIR - The name of the theme directory
 */
if (!defined('MOODLE_THEME_DIR')) {
    define('MOODLE_THEME_DIR', 'theme');
}

if (!defined('MOODLE_SFTP_DIR')) {
    /**
     * MOODLE_THEME_DIR - The name of the theme directory
     */
    define('MOODLE_SFTP_DIR', 'sftp_uploads');
}

/**
 * The Moodle Manager class
 *
 * The class is supposed to manage the on-disk code, database and data directory of a Moodle site.
 *
 * @author    Tyler Bannister <tyler.bannister@remote-learner.net>
 * @copyright 2012-2014 Remote-Learner, Inc.
 */
class RLSCRIPTS_Moodle_Manager extends RLSCRIPTS_Manager {
    /**
     * Used to specify that an addon should be added.
     */
    const ACTION_ADD = 'add';

    /**
     * Used to specify that an addon should be removed.
     */
    const ACTION_REMOVE = 'remove';

    /**
     * Used to specify that an addon should be upgraded.
     */
    const ACTION_UPDATE = 'update';

    /**
     * Tells us where legacy maintenance files go.
     */
    const MAINT_FILE_LEGACY = '1/maintenance.html';

    /**
     * Tells us where modern maintenance files go.
     */
    const MAINT_FILE_MODERN = 'climaintenance.html';

    /**
     * Used to specify disk source
     */
    const SOURCE_DISK = 1;

    /**
     * Used to specify database source
     */
    const SOURCE_DB   = 2;

    /** @var string The actions that need to be requested from the addon_manager */
    protected $actions = array(self::ACTION_ADD => array(), self::ACTION_REMOVE => array(), self::ACTION_UPDATE => array());

    /** @var string How the site is blocked, blank string for not blocked */
    protected $blocked = '';

    /** @var array A list of the available branches for variants */
    protected $branches = array(
        'default'                => array(19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31),
        'rlmoodle.elis.git'      => array(19, 21, 22, 23, 24, 25),
        'rlmoodle.elisfiles.git' => array(23, 24, 25),
        'rlmoodle.gao.git'       => array(20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31),
    );

    /** @var The config prep file */
    protected $cfgprepfile = '/etc/php.d/config_prep.php';

    /** @var array The list of plugins from the Moodle site database */
    protected $db_plugins = null;

    /** @var array The list of plugins from the Moodle site directory */
    protected $disk_plugins = null;

    /** @var array The minimal required Moodle files */
    protected $files = array(
        'config.php', 'file.php', 'help.php', 'index.php', 'install.php', 'version.php'
    );

    /** @var string The path to incron.d directory for MASS */
    protected $incron_path = '/etc/incron.d/massd';

    /** @var object The proper name of the object that we manage */
    protected $name = 'Moodle';

    /** @var string The plugin command directory */
    protected $plugincommanddir = '/var/run/mass';

    /** @var array An array of plugin types */
    protected $plugintypes = null;

    /** @var array The list of Moodle releases plus required version to upgrade to that version. */
    protected $releases = array(
        '1.9.0'  => array('version' => 2007101509, 'branch' => 19, 'php' => '4.3.0', 'mysql' => '5.0.25'),
        '2.0.0'  => array('version' => 2010112400, 'branch' => 20, 'php' => '5.2.8', 'mysql' => '5.0.25', 'required' => '1.9.0'),
        '2.1.0'  => array('version' => 2011070100, 'branch' => 21, 'php' => '5.3.2', 'mysql' => '5.0.25', 'required' => '1.9.0'),
        '2.2.0'  => array('version' => 2011120500, 'branch' => 22, 'php' => '5.3.2', 'mysql' => '5.0.25', 'required' => '1.9.0'),
        '2.2.9'  => array('version' => 2011120509, 'branch' => 22, 'php' => '5.3.2', 'mysql' => '5.0.25', 'required' => '1.9.0'),
        '2.2.11' => array('version' => 2011120511, 'branch' => 22, 'php' => '5.3.2', 'mysql' => '5.0.25', 'required' => '1.9.0'),
        '2.3.0'  => array('version' => 2012062500, 'branch' => 23, 'php' => '5.3.2', 'mysql' => '5.1.33', 'required' => '2.2.0'),
        '2.4.0'  => array('version' => 2012120300, 'branch' => 24, 'php' => '5.3.2', 'mysql' => '5.1.33', 'required' => '2.2.0'),
        '2.5.0'  => array('version' => 2013051400, 'branch' => 25, 'php' => '5.3.3', 'mysql' => '5.1.33', 'required' => '2.2.9'),
        '2.6.0'  => array('version' => 2013111800, 'branch' => 26, 'php' => '5.3.3', 'mysql' => '5.1.33', 'required' => '2.2.11'),
        '2.7.0'  => array('version' => 2014051200, 'branch' => 27, 'php' => '5.4.4', 'mysql' => '5.5.31', 'required' => '2.2.11'),
        '2.7.14' => array('version' => 2014051214, 'branch' => 27, 'php' => '5.4.4', 'mysql' => '5.5.31', 'required' => '2.2.11'),
        '2.8.0'  => array('version' => 2014111000, 'branch' => 28, 'php' => '5.4.4', 'mysql' => '5.5.31', 'required' => '2.2.11'),
        '2.9.0'  => array('version' => 2015051100, 'branch' => 29, 'php' => '5.4.4', 'mysql' => '5.5.31', 'required' => '2.2.11'),
        '3.0.0'  => array('version' => 2015111600, 'branch' => 30, 'php' => '5.4.4', 'mysql' => '5.5.31', 'required' => '2.2.11'),
        '3.1.0'  => array('version' => 2016052300, 'branch' => 31, 'php' => '5.4.4', 'mysql' => '5.5.31', 'required' => '2.7.14'),
    );

    /** @var array The minimal configuration variables required to have a valid Moodle config */
    protected $required_configvars = array(
        'dbhost',
        'dbname',
        'dbuser',
        'dbpass',
        'wwwroot',
        'dirroot',
        'dataroot',
    );

    /** @var array The minimal required Moodle subdirectories */
    protected $subdirectories = array(
        'auth',   'backup', 'blocks', 'calendar', 'course', 'enrol',   'error', 'files',
        'filter', 'grade',  'lang',   'lib',      'login',  'message', 'mod',   'pix',
        'rss',    'theme',  'user',  'userpix'
    );

    /** @var object The type of object that we manage */
    protected $type = 'moodle';

    /**
     * Constructor
     *
     * @param object|array $cfg A Moodle config array or object
     * @param object $cli A command line interface object for user interaction
     * @param object $shell A shell object for running shell commands
     * @param object $com A communication object for sending data to the dashboard
     * @param object $error An error handling object
     */
    public function __construct($cfg = null, $cli = null, $shell = null, $com = null, $error = null, $webserver = null) {
        parent::__construct($cfg, $cli, $shell, $com, $error, $webserver);

        $helper = new helper_moodle();
        $this->plugintypes = $helper->get_plugin_types();
    }

    /**
     * Callback function for helper scripts.
     *
     * @param string $line A line of STDOUT.
     */
    public function _database_helper_callback($line) {
        $line = trim($line);
        $this->log($line);

        $match = array();

        if (preg_match('/^\-\-\>(.*)$/', $line, $match)) {
            $status = trim(strtolower($match[1]));

            if ($status == 'system') {
                $status = 'core tables';
            }

            $this->status('Setting up Moodle database ('.$status.')', true);
        } else if (preg_match('/\+\+ Success \+\+/', $line, $match)
                || preg_match('/\.\.\. done!/', $line, $match)) {
            $this->success();
        } else if (preg_match('/Plugin (.*) is defective/', $line, $match)) {
            $this->warning("Plugin {$match[1]} upgrade failed.");
        } else if (preg_match('/Cannot downgrade (.*) from (\d+) to (\d+)./', $line, $match)) {
            $this->error("Plugin {$match[1]} version ({$match[3]}) is lower than database version ({$match[2]})");
        } else if (preg_match('/>>> ([\w ]+)/', $line, $match)) {
            $this->status($match[1]);
        } else if (DEBUG) {
            $this->success();
            $this->message("Debug: $line");
        }

    }

    /**
     * Add a new action to the list of desired actions
     *
     * @param string $action The action to take
     * @param string $type The addon type to take the action with
     * @param string $name The addon name to take the action with
     * @return bool True for success
     */
    public function addon_add_action($action, $type, $name) {
        if (!is_string($action)) {
            $this->error(__METHOD__.': Action must be a string.');
        }
        if (!is_string($type)) {
            $this->error(__METHOD__.': Type must be a string.');
        }
        if (!is_string($name)) {
            $this->error(__METHOD__.': Name must be a string.');
        }

        if (!array_key_exists($action, $this->actions)) {
            $this->warning(__METHOD__.": Attempted to queue unknown addon action: $action\n");
            return false;
        }
        if (!array_key_exists($type, $this->plugintypes)) {
            $this->warning(__METHOD__.": Attempted to queue action for unknown plugin type: $type\n");
            return false;
        }
        $frankenname = "{$type}_{$name}";
        $this->actions[$action][$frankenname] = $frankenname;

        return true;
    }

    /**
     * Add any available addons that are not installed
     *
     * Note: This method will replace any already installed addons in the provided list.
     *
     * @param string $addons List of addons that should be installed, checks the database when blank.
     * @param int $branch The branch number for the plugins
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return bool True for success
     */
    public function addon_add_missing($addons = array(), $branch = 0, $sandboxdirroot = '') {
        $this->prepare_git();

        if (count($addons) == 0) {
            $addons = $this->get_plugins(static::SOURCE_DB);
        }
        if ($branch == 0) {
            $branch = $this->determine_moodle_branch_number($this->git->branch());
        }

        $all = helper_moodle::LEVEL_GAO + helper_moodle::LEVEL_PLUS + helper_moodle::LEVEL_THIRD;
        $this->message("\nCollecting plugin information:");
        $dashboard = $this->get_dashboard_addons($all, $branch);
        $this->message("\nAdding missing addons:");
        foreach ($addons as $type => $typelist) {
            foreach ($typelist as $name => $data) {
                if (is_array($data)) {
                    $data = new RLSCRIPTS_Moodle_Addon($this, $data['type'], $data['name']);
                }
                $path = $data->path;
                $data->set_sandboxpath($sandboxdirroot);
                $available = array_key_exists($data->fullname, $dashboard);
                $missing = (!file_exists($path) || is_link($path) || !file_exists("$path/version.php"));
                if ($available && $missing) {
                    $this->status("Plugin {$data->fullname} is missing, checking dependencies");
                    $good = $this->addon_check_dependencies($dashboard, "{$data->fullname}");
                    if ($good) {
                        $this->success();
                        $this->status("Adding {$data->fullname} now");
                        if ($data->add()) {
                            $this->success();
                        } else {
                            $this->failure();
                        }
                    } else {
                        $this->failure();
                    }
                }
            }
        }
        return true;
    }

    /**
     * Check if a plugin's dependencies are installed or can be installed
     *
     * @param ref $available A reference to a list of available plugins
     * @param string $fullname The addon whose dependencies need to be checked.
     * @param array $stack The list of plugins that we're checking (for loop detection).
     * @param string $source Which dependency
     * @return bool True if the dependencies are installed or will be.
     */
    protected function addon_check_dependencies(&$available, $fullname, $stack = array(), $source = 'reference') {
        $good = true;
        $addon = $available[$fullname];
        $dependencies = $addon->dependencies;
        foreach ($dependencies[$source] as $dependency => $version) {
            if (array_key_exists($dependency, $stack)) {
                $this->warning("Dependency loop detected: $dependency depends on itself!");
                continue;
            }
            $stack[$dependency] = true;
            list($type, $name) = explode('_', $dependency, 2);
            // Check if it's installed on the site.
            $installed = (array_key_exists($type, $this->db_plugins) && array_key_exists($name, $this->db_plugins[$type]));
            // Check if the current version on disk meets the requirements
            $disk = ($installed && ($this->db_plugins[$type][$name]->versions['disk'] > 0));
            $sufficient = ($installed && ($this->db_plugins[$type][$name]->versions['disk'] >= $version));
            // Check if the version in the source repository meets the requirements.
            $reference = (array_key_exists($dependency, $available) && ($available[$dependency]->versions[$source] >= $version));
            $upgradeable = ($reference && $this->addon_check_dependencies($available, $dependency, $stack, $source));
            // Dependencies not met if:
            //      Not installed
            //      On disk and disk version not sufficient
            //      Off disk and source version not sufficient
            if (!$installed || ($disk && !$sufficient) || (!$disk && !$upgradeable)) {
                $good = false;
                break;
            }
            unset($stack[$dependency]);
        }
        return $good;
    }

    /**
     * Update all addons that have available updates
     *
     * @param string $addons List of addons that should be installed, checks the database when blank.
     * @param int $branch The branch to process updates for (affects plugin availability)
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return bool True for success
     */
    public function addon_update_all($addons = array(), $branch = 0, $sandboxdirroot = '') {
        if (count($addons) == 0) {
            $addons = $this->get_plugins(static::SOURCE_DB);
        }
        if ($branch == 0) {
            $branch = $this->determine_moodle_branch_number($this->git->branch());
        }

        $all = helper_moodle::LEVEL_GAO + helper_moodle::LEVEL_PLUS + helper_moodle::LEVEL_THIRD;
        $this->message("\nCollecting plugin information:");
        $available = $this->get_dashboard_addons($all, $branch);

        $this->message("\nUpdating addons:");
        foreach ($addons as $type => $typelist) {
            foreach ($typelist as $name => $addon) {
                if (!is_object($addon)) {
                    $addon = new RLSCRIPTS_Moodle_Addon($this, $type, $name);
                }
                if (array_key_exists($addon->fullname, $available)) {
                    $this->message("Updating {$addon->fullname}:");
                    $addon->set_sandboxpath($sandboxdirroot);
                    if ($addon->update()) {
                        $this->success();
                    } else {
                        $this->failure();
                    }
                }
            }
        }
        return true;
    }

    /**
     * Check all plugins prior to upgrade to see if they have an updated stable version available
     *
     * @param int $branch The branch to upgrade to
     * @return bool True for success
     */

    public function check_plugins_upgrade_all($branch) {
        $this->prepare_git();
        if (count($addons) == 0) {
            $addons = $this->get_plugins(static::SOURCE_DB);
        }

        $all = helper_moodle::LEVEL_GAO + helper_moodle::LEVEL_PLUS + helper_moodle::LEVEL_THIRD;
        $this->message("\nCollecting plugin information:");
        $available = $this->get_dashboard_addons($all, $branch);
        $this->message("\nChecking to see if all plugins have an updated stable version available.");
        $notpresent = 0;
        foreach ($addons as $type => $typelist) {
            foreach ($typelist as $name => $addon) {
               if (!array_key_exists($addon->fullname, $available)) {
                   $notpresent += 1;
                   $addon->set_sandboxpath($sandboxdirroot);
                   $this->message("This plugin does not have an updated stable version available {$addon->fullname}:");
               }
            }
        }
        if ($notpresent == 0) {
            $this->message("All plugins are present in the updated branch.");
        } else {
            $this->message(" Some plugins are not present in the updated stable branch. This should be checked prior to running the upgrade");
        }
        return true;
    }


    /**
     * Upgrade all installed addons to a new version
     *
     * @param string $addons List of addons that should be installed, checks the database when blank.
     * @param int $branch The branch to upgrade to
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return bool True for success
     */
    public function addon_upgrade_all($addons = array(), $branch = 0, $sandboxdirroot = '') {
        $this->prepare_git();
        if (count($addons) == 0) {
            $addons = $this->get_plugins(static::SOURCE_DB);
        }
        if ($branch == 0) {
            $branch = $this->determine_moodle_branch_number($this->git->branch());
        }

        $all = helper_moodle::LEVEL_GAO + helper_moodle::LEVEL_PLUS + helper_moodle::LEVEL_THIRD;
        $this->message("\nCollecting plugin information:");
        $available = $this->get_dashboard_addons($all, $branch);
        $this->message("\nUpgrading addons:");
        $upgraded = 0;
        $removed = 0;
        foreach ($addons as $type => $typelist) {
            foreach ($typelist as $name => $addon) {
                if (!is_object($addon)) {
                    $addon = new RLSCRIPTS_Moodle_Addon($this, $type, $name);
                }
                if (array_key_exists($addon->fullname, $available)) {
                    $addon->set_sandboxpath($sandboxdirroot);
                    $this->message("Upgrading {$addon->fullname}:");
                    if ($addon->upgrade($branch)) {
                        $this->success();
                        $upgraded += 1;
                    } else {
                        $this->failure();
                    }
                } else if (file_exists($addon->path.'/.git')) {
                    // Only remove non-core plugins that don't have upgraded versions available.
                    $this->message("New version of {$addon->fullname} not available, removing:");
                    if ($addon->remove()) {
                        $this->success();
                        $removed += 1;
                    } else {
                        $this->failure();
                    }
                }
            }
        }
        if ($upgraded == 0) {
            $this->message("No plugins were upgraded.");
        } else {
            $this->message("$upgraded plugins were upgraded.");
        }
        if ($removed != 0) {
            $this->message("$removed plugins were removed.");
        }
        return true;
    }

    /**
     * Request actions from the automation script
     */
    public function addon_write_request() {
        $config = $this->get_config();
        // Write to a tempfile to make requests atomic.
        $tmpfile = tempnam(sys_get_temp_dir(), 'addon_');
        $file = $this->plugincommanddir.'/'.basename($tmpfile);
        $handle = fopen($tmpfile, 'w+');
        fwrite($handle, "site {$config->dirroot}\n");
        // This list species the command order: remove then add then update.
        $order = array(self::ACTION_REMOVE, self::ACTION_ADD, self::ACTION_UPDATE);
        foreach ($order as $action) {
            $list = $this->actions[$action];
            foreach ($list as $plugin) {
                fwrite($handle, "$action $plugin\n");
            }
        }
        fclose($handle);
        // Move the finished request file into place (use copy because of inter-filesystem move bug).
        copy($tmpfile, $file);
        unlink($tmpfile);
    }

    /**
     * Backup MySQL database
     *
     * @param string $file The file to save the data in
     */
    public function backup_mysql($file) {
        $cfg = $this->get_config();

        // Backup the database.
        $this->status("Backing up MySQL database to '$file'");
        // If we don't specify the defaults file explicitly, mysqldump doesn't work with sudo
        $cmd = "mysqldump --defaults-file=/root/.my.cnf -h {$cfg->dbhost} -u root --opt {$cfg->dbname} | gzip > $file";
        if ($this->shell->exec($cmd) !== 0) {
            $this->error($this->shell->stderr());
        }

        $this->shell->reset();
        $this->success();
    }

    /**
     * Prevent the Moodle site from being accessed.
     *
     * @param string $type The type of block to set up.
     */
    public function block($type = 'htaccess') {
        $success = false;

        $cfg = $this->get_config();

        if ($type == 'maintenance') {
            $this->status('Putting site into maintenance mode');
            $success = $this->create_maintenance_file();
        } else {
            $this->status('Block site access via .htaccess file');
            $success = $this->webserver->create_htaccess_rules($cfg->dirroot);
        }
        if ($success) {
            $this->blocked = $type;
        }
        return $this->result($success);
    }

    /**
     * Calls a helper function
     *
     * @param string $helper The helper to be called
     * @param string $options The options to call the helper with
     * @param string $message A message to print if the called helper fails
     * @param array|string $callback A callable function to handle output from the helper
     * @return bool True on successful install, false otherwise
     */
    public function call_helper($helper, $options, $message = '', $callback = '') {
        global $_RLSCRIPTS;

        $cmd = "{$_RLSCRIPTS->root}/moodle/helper/$helper";
        return parent::call_helper($cmd, $options, $message, $callback);
    }

    /**
     * Check whether this Moodle can be migrated to GAO
     *
     * @return boolean/string True if gao upgradeable, an error string if not.
     */
    public function check_for_gao_upgrade() {
        $this->prepare_git();

        $gao        = false;
        $elis       = false;
        $repository = basename($this->git->repository(), '.git');
        $branches   = $this->git->branches('MOODLE_\d+_STABLE');

        $cfg = $this->get_config();

        if (!$this->is_moodle($cfg->dirroot)) {
            $this->error('This does not appear to be a moodle site.');
        }

        $submodule = false;
        $submodules = $this->git->submodule(true);

        if (sizeof($submodules) > 0) {
            $submodule = true;
        }

        $gao_branch = false;

        foreach ($branches as $branch => $current) {
            $matches = array();

            if (preg_match('/MOODLE_(\d+)_STABLE/', $branch, $matches)) {

                if ($matches[1] >= 21) {
                    $gao_branch = true;
                }
            }
        }

        if ($submodule && $gao_branch) {
            $gao = true;
        }

        return $gao;
    }

    /**
     * Check whether this upgrade will change major revision numbers
     *
     * @param string $newbranch The name of the branch to upgrade to
     * @return boolean/string True if gao upgradeable, an error string if not.
     */
    public function check_for_major_upgrade($newbranch) {
        $major = false;
        $oldbranch = $this->git->branch();

        $new = substr($newbranch, 7, 1);
        $old = substr($oldbranch, 7, 1);

        if ($old < $new) {
            $major = true;
        }
        return $major;
    }

    /**
     * Check for unknown plugins
     *
     * @param string $repository The repository to use as the comparison base
     * @return array A two-dimensional array of extra plugins grouped by type.
     */
    public function check_unknown_plugins($repository = '', $branch = '') {
        $known   = array();
        $unknown = array();

        $this->status('Checking for unknown plugins');

        if ($repository == '') {
            $repository = $this->git->repository();
        }

        if ($branch == '') {
            $branch = $this->git->branch();
        }
        $version = substr($branch, 7, 1);

        $type    = 'M';
        if (strpos($repository, 'elis') !== false) {
            $type = 'E';
        }
        $code = $type . $version;

        $plugins = new RLSCRIPTS_Moodle_Plugins();

        if (array_key_exists($code, $plugins->versions)) {

            foreach ($this->plugintypes as $name => $type) {
                $unknown[$name] = array();
                $known[$name]  = $plugins->get_list($code, $name);

                if ($known[$name] === false) {
                    $this->warning("Unable to get plugin list for $name!");
                    continue;
                }

                $list = $this->get_plugins(self::SOURCE_DISK, array($name));

                if ($list == false) {
                    $this->warning("Failed to get plugin list for $name.");
                    continue;
                }
                foreach ($list[$name] as $plugin) {

                    if (!array_key_exists($plugin, $known[$name])) {
                        $unknown[$name][] = $plugin;
                    }
                }
            }

            $this->success();
        } else {
            $this->failure();
            $this->warning("Unable to determine known plugins for branch $branch");
        }

        return $unknown;
    }

    /**
     * Ask the user to confirm the major version upgrade.
     *
     * @param string $branch The branch to migrate the repository to
     */
    public function confirm_major_upgrade($newbranch) {
        $confirmed  = false;
        $oldbranch  = $this->git->branch();
        $repository = $this->git->repository();

        $old = substr($oldbranch, 7, 2);
        $new = substr($newbranch, 7, 2);

        $cfg = $this->get_config();

        $this->message('    This appears to be a major version upgrade.');

        $disallowed = $this->check_unknown_plugins($repository);
        $this->message('');

        foreach ($disallowed as $type => $list) {
            if (sizeof($list) > 0) {
                $this->message('    The following '. $this->plugintypes[$type]['name']
                              ." will have to be removed:\n        ". implode(', ', $list));
            }
        }

        $this->validate_db($cfg->dirroot);

        $response = $this->prompt('   Proceed with upgrades? (y/n)', '//', 'y');

        if (strtolower(substr($response, 0, 1)) == 'y') {
            $confirmed = true;
        }
        return $confirmed;
    }

    /**
     * Convert ELIS to Plugins
     *
     * Used to convert rlmoodle.elis and rlmoodle.elisfiles to rlmoodle.gao (M25 -> M26+)
     *
     * @param string $repository The repository name, used to determine plugins that must be activated.
     */
    public function convert_elis_to_plugins($repository) {
        $this->message('Converting ELIS to Plugins');
        $plugins = array(
            'elis'      => array('block_courserequest', 'block_elisadmin', 'enrol_elis', 'local_eliscore', 'local_elisprogram', 'local_elisreports'),
            'elisfiles' => array('auth_elisfilessso', 'block_repository', 'repository_elisfiles'),
        );
        $branch = $this->determine_moodle_branch_number($this->git->branch());
        $parts = explode('.', $repository, 3);
        $type = $parts[1];
        $groups = array();

        if ($type == 'elis') {
            $groups = array('elis', 'elisfiles');
        } else if ($type == 'elisfiles') {
            $groups = array('elisfiles');
        }

        if ($branch == 26) {
            foreach ($groups as $group) {
                $this->gaoplus_enable($branchversion, $group, true);
                $this->message("  GAO Plus group $group has been enabled");
                $addons = true;
            }
        } else if ($branch > 26) {
            foreach ($groups as $group) {
                foreach ($plugins[$group] as $plugin) {
                    list($type, $name) = explode('_', $plugin, 2);
                    $addon = new RLSCRIPTS_Moodle_Addon($this, $type, $name);
                    $addon->add();
                    $this->message("  ELIS Plugin {$type}_{$name} added.");
                    $addons = true;
                }
            }
        }
    }

    /**
     * Create the data root directory
     */
    public function create_dataroot() {
        $config = $this->get_config();
        $this->prepare_git();
        $branch = $this->determine_moodle_branch_number($this->git->branch());
        $m20 = ($branch >= 20);

        if (!$this->create_directory($config->dataroot, self::OWNER_DATAROOT)) {
            $this->error("Unable to create directory '{$config->dataroot}'");
        }

        // We need to create it if it's set to a not-empty string value and it doesn't exist.
        $needed = (!empty($config->datarootextended));
        if ($needed && !$this->create_directory($config->datarootextended, self::OWNER_DATAROOT)) {
            $this->error("Unable to create directory '{$config->datarootextended}'");
        }

        $themedir = $config->dataroot.'/'.MOODLE_THEME_DIR;
        if ($m20 && !$this->create_directory($themedir, self::OWNER_DATAROOT)) {
            $this->warning("Unable to create directory: $themedir");
        }

        $sftpdir = $config->dataroot.'/'.MOODLE_SFTP_DIR;
        if ($m20 && !$this->create_directory($sftpdir, self::OWNER_DATAROOT)) {
            $this->warning("Unable to create directory: $sftpdir");
        }

        $this->create_dataroot_symlinks();
    }

    /**
     * Create the symbolic links under the dataroot directory.
     *
     * If the directories are left to their default standard location, they will be created by Moodle;
     * otherwise, we create our own custom non-standard directories.
     * Note: This function is not unit testable with vfsStream because of the symlink command.
     */
    public function create_dataroot_symlinks() {
        $config = $this->get_config();
        $dirs = array(
            'cachedir'      => 'cache',
            'sessiondir'    => 'sessions',
            'mucdir'        => 'muc',
            'lockdir'       => 'lock',
            'localcachedir' => 'localcache',
        );

        foreach ($dirs as $setting => $dir) {
            $default = $config->dataroot.'/'.$dir;
            $needed = (!empty($config->{$setting}) && ($config->{$setting} !== $default));

            if ($needed && !$this->create_directory($config->{$setting}, self::OWNER_DATAROOT)) {
                $this->error("Unable to create directory '".$config->{$setting}."'");
            } else if ($needed && !$this->create_symlink($config->{$setting}, $default)) {
                $this->warning("Failed creating symbolic link from '$default' to '".$config->{$setting}."'");
            }
        }
    }

    /**
     * Create the defaults file
     *
     * Include the standard local/defaults.php file to be modified by other processes per HOSSUP-3662.
     */
    public function create_defaults_file() {
        $config = $this->get_config();

        $this->status('Copying local/defaults.php file for language, country and timezone defaults');
        $dir = $config->dirroot.'/local';
        if (!$this->create_directory($dir, self::OWNER_DIRROOT)) {
            $this->error("The directory \"{$dir}\" cannot be created.");
        }
        $file = "$dir/defaults.php";
        if (!is_file($file)) {
            if (!copy(dirname(__FILE__).'/../../../../automation/moodle/defaults.php', $file)) {
                $this->error("The defaults.php file cannot be copied to {$dir}.");
            }
        }
        $this->success();
    }

    /**
     * Create the incron file for MASS system
     *
     * To be used if the site is installed on a server that is not under puppet control.
     *
     * @return bool True for success, false for failure
     */
    public function create_incron_entry() {
        $config = $this->get_config();
        $file = $this->get_incron_path();
        $oldfile = dirname($this->get_incron_path()).'/mass';
        $success = false;

        $lines = array();
        // Delete the old file it it exists.
        if (file_exists($oldfile) && (basename($config->dirroot) == 'moodle_prod')) {
            unlink($oldfile);
        }

        // Only write the incron setup if the directory is not already monitored.
        if (is_dir(dirname($file)) && !file_exists($file)) {
            $handle = fopen($file, 'a');
            if ($handle === false) {
                $this->warning("Unable to write to MASS incron file: $file");
                return false;
            }
            fwrite($handle, '/var/run/mass IN_CREATE /rlscripts/automation/moodle/dispatch $@/$#'."\n");
            fclose($handle);
        }
    }

    /**
     * Create the file in Moodledata that is supposed to enable maintenance mode on a given site.
     *
     * Note: We do not use the Moodle built-in maintenance command because it becomes unstable when
     *       used across versions.  For example, while Moodle 2.0-2.5 does not create a
     *       climaintenance.html file, it still shutdowns the site if it is present.  Starting with
     *       Moodle 2.6, it started creating the file.  Therefore, if support tries to revert a
     *       Moodle 2.6 or later site to Moodle 2.5 or earlier the site would become stuck in
     *       maintenance mode until the wayward climaintance.html file is deleted.
     *       Since manually creating and deleting the file is acceptable in all current versions of
     *       Moodle, we take that approach to managing the maintance mode.
     *       Additionally using climaintenancy.html in Moodle 2.0 - 2.5 will prevent the client
     *       admin user from logging in and making changes while the upgrade is happening.  Using
     *       the Moodle admin/cli/maintenance.php command does not block the admin user until
     *       Moodle 2.6.
     *
     * @param  string $dirroot The filesystem path to the dirroot for the site.
     * @param  string $dataroot The filesystem path to the dataroot for the site.
     * @return bool True if the maintenance file exists, False if something went wrong.
     */
    protected function create_maintenance_file() {
        global $_RLSCRIPTS;
        $config = $this->get_config();

        $filename = static::MAINT_FILE_MODERN;
        if ($this->determine_moodle_branch_number($config->rlscripts_git_branch) < 20) {
            $filename = static::MAINT_FILE_LEGACY;
        }
        $destination = $config->dataroot.'/'.$filename;

        $source = $_RLSCRIPTS->root.'/lib/html/';
        if (file_exists($_RLSCRIPTS->dataroot.'/conf/httpd/'.static::MAINT_FILE_MODERN)) {
            $source = $_RLSCRIPTS->dataroot.'/conf/httpd/';
        }
        $source .= $filename;

        if (file_exists($destination)) {
            return true;
        }

        if (!is_dir(dirname($destination))) {
            if (false === mkdir(dirname($destination), 0755, true)) {
                return false;
            }
        }
        if (false === copy($source, $destination)) {
            return false;
        }

        $apacheuser = get_apache_username();
        chown($destination, $apacheuser);
        chgrp($destination, $apacheuser);

        return true;
    }

    /**
     * Remove all the GAO plugins from the Moodle repository
     */
    public function delete_gao_plugins() {
        $version     = 'M2';
        $roots       = array();
        $directories = array();
        $repository  = $this->git->repository();

        $this->status('Deleting GAO and GAO+ plugins that might prevent upgrade');

        if (strpos($repository, 'elis') !== false) {
            $version = 'E2';
        }

        $plugins = new RLSCRIPTS_Moodle_Plugins();

        foreach ($this->plugintypes as $name => $type) {
            $known[$name]  = $plugins->get_list($version, $name);

            if (!is_array($known[$name])) {
                $this->warning("Dashboard did not return a valid list of $name type plugins.");
            }

            // Git doesn't handle extra directories well, so skip anything that doesn't exist.
            if (is_dir($this->config->dirroot.$type['path'])) {
                $roots[] = $type['path'];

                $list = $this->get_plugins(self::SOURCE_DISK, array($name));
                foreach ($list[$name] as $plugin) {

                    if (is_array($known[$name]) && array_key_exists($plugin, $known[$name])) {
                        $source = $known[$name][$plugin];

                        if (($source == MOODLELIB_SOURCE_GAO) || ($source == MOODLELIB_SOURCE_GAOPLUS)) {
                            $directories[] = $type['path'] .'/'. $plugin;
                        }
                    }
                }
            }
        }

        $actions[] = array(
            'cmd'    => 'checkout',
            'args'   => implode(' ', $roots),
            'status' => '',
        );

        $this->directory_delete($directories);

        // In case of error, checkout directories to restore accidentally deleted files
        $this->process_git($actions);
        $this->success();
    }

    /**
     * Delete the non standard plugins
     *
     * This function is necessary for Moodle 2.7+ upgrades.
     */
    public function delete_nonstandard_plugins() {
        $addons = $this->get_plugins(self::SOURCE_DISK);

        foreach ($addons as $type => $list) {
            foreach ($list as $name) {
                $addon = new RLSCRIPTS_Moodle_Addon($this, $type, $name);
                // All nonstandard plugins should have a .git directory and no core plugins should have one.
                if (file_exists($addon->path.'/.git')) {
                    $addon->remove();
                }
            }
        }
    }

    /**
     * Delete the unknown plugins
     *
     * This function is necessary for Moodle 1 to 2 upgrades
     */
    public function delete_unknown_plugins() {
        $actions     = array();
        $directories = array();

        $unknown = $this->check_unknown_plugins();

        foreach ($unknown as $type => $list) {

            foreach ($list as $item) {
                $directories[] = $this->plugintypes[$type]['path'] .'/'. $item;
            }

            // Command to undo improper deletes
            $actions[] = array(
                'cmd'    => 'checkout',
                'args'   => $this->plugintypes[$type]['path'],
                'status' => '',
            );

        }

        $this->directory_delete($directories);

        // In case of error, checkout directories to restore accidentally deleted files
        $this->process_git($actions);
    }

    /**
     * Determine the numeric moodle version
     *
     * @param string $version
     */
    protected function determine_moodle_branch_number($branch) {
        $matches = array();
        $number = 0;

        if (preg_match('/MOODLE_(\d+)_STABLE/', $branch, $matches)) {
            $number = $matches[1];
        }

        return $number;
    }

    /**
     * Delete a directory, used to remove modules and blocks on upgrade
     *
     * @param array $directories An array listing the directories to remove
     */
    public function directory_delete($directories) {
        $cfg        = $this->get_config();
        $root       = $cfg->dirroot;
        $rootlength = strlen($root);

        // Remove any possible remaining conflicts
        foreach ($directories as $directory) {

            $path = $directory;
            $test = substr($path, 0, $rootlength);
            // Prevent disaster by limiting directories that can be deleted.
            if ($root != $test) {
                $path = $root .'/'. $directory;
            }

            $this->directory_delete_unsafe($path);
        }
    }

    /**
     * Load configuration for a specific moodle site
     *
     * @param string $path The path to the Moodle site
     * @param boolean $refresh Whether to force load from disk
     * @return bool|object The config object if successful, false for failure
     */
    public function fetch_config($path, $refresh = true) {
        return RLSCRIPTS_Moodle::getConfig($path, $refresh);
    }

    /**
     * Fetch a list of Moodle sites.
     *
     * @return array An array of sites.
     */
    protected function fetch_list() {
        return RLSCRIPTS_Moodle_Cache::get_list();
    }

    /**
     * Fix the minimum necessary file permissions for the moodle installation.
     *
     * Currently fixes the Moodle code files and Moodle cache files.
     */
    public function fix_permissions_minimal() {
        $config = $this->get_config();

        $roots = array('.git');
        $execs = array(
            'paths'    => array('/.git/hooks/'),
            'subpaths' => array('filter/algebra/algebra2tex.pl', 'filter/tex/mimetex.darwin', 'filter/tex/mimetex.linux'),
        );

        $this->fix_permissions_recursive($config->dirroot, $this->owners['dirroot'], array(), $roots, $execs);

        $dirs = array(
            'cachedir'      => 'cache',
            'sessiondir'    => 'sessions',
            'localcachedir' => 'localcache',
            'mucdir'        => 'muc', // Note: mucdir is not actually a setting.
        );

        foreach ($dirs as $setting => $default) {
            $dir = "{$config->dataroot}/{$default}";
            if (!empty($config->$setting)) {
                $dir = $config->$setting;
            }
            $this->fix_permissions_recursive($dir, $this->owners['dataroot']);
        }
    }

    /**
     * Fix the file permissions for the moodle installation.
     */
    public function fix_permissions() {
        $config = $this->get_config();

        if (is_dir("{$config->dirroot}/.git")) {
            $result = $this->disable_filemode_tracking();
            if ($result === false) {
                $this->warning('Failed disabling filemode tracking');
            }
        }

        $datarootlen = strlen($config->dataroot);
        $nomads = array(
                'cachedir', 'datarootextended', 'langcacheroot', 'langmenucachefile', 'localcachedir', 'muc',
                'sessiondir', 'tempdir', 'themedir'
        );
        $externals = array();
        foreach ($nomads as $nomad) {
            if (!(empty($config->$nomad) || (substr($config->$nomad, 0, $datarootlen) == $config->dataroot))) {
                $externals[] = $config->$nomad;
            }
        }

        $roots = array('.git');
        $execs = array(
            'paths'    => array('/.git/hooks/'),
            'subpaths' => array('filter/algebra/algebra2tex.pl', 'filter/tex/mimetex.darwin', 'filter/tex/mimetex.linux'),
        );

        $this->message($config->dirroot);
        $this->fix_permissions_recursive($config->dirroot, $this->owners['dirroot'], $externals, $roots, $execs);

        if (preg_match('/^\s*1\.9/', $config->release)) {
            chmod($config->dirroot.'/theme', 0775);

            if (is_dir($config->dirroot.'/.git')) {
                $this->shell->cd($config->dirroot);
                $this->shell->stdoutReset();
                $this->shell->exec('git ls-files --exclude-standard --others -- theme/');

                foreach ($this->shell->stdoutArray() as $path) {
                    if (!empty($path)) {
                        chmod(dirname($config->dirroot.'/'.$path), 0775);
                        chmod($config->dirroot.'/'.$path, 0664);
                    }
                }

                $this->shell->reset();
            }
        }

        $this->message("\n{$config->dataroot}");
        $this->fix_permissions_recursive($config->dataroot, $this->owners['dataroot'], $externals);

        foreach ($externals as $external) {
            $this->message("\n$external");
            $this->fix_permissions_recursive($external, $this->owners['dataroot']);
        }
    }

    /**
     * Get the list of plugins based on the supplied name
     *
     * @param string $moodleversion A two digit Moodle version identifier
     * @param string $gaoplusname GAO+ plugin grouping name
     */
    protected function gaoplus_get_plugin_list($moodleversion, $gaoplusname) {
        $pluginarray = $this->gaoplus_directory_status($moodleversion);
        $plugins = array();

        if (isset($pluginarray[$gaoplusname]) && !empty($pluginarray[$gaoplusname])) {
            $plugins = $pluginarray[$gaoplusname];
            // Symlink all the plugin components for the specified GAO+ grouping name.
        } else if (isset($pluginarray[''][$gaoplusname])) {
            $plugins = array($gaoplusname => $pluginarray[''][$gaoplusname]);
        } else {
            $message = "Unknown GAO+ name specified ($gaoplusname).\n".
                       "Please specify a plugin group or an ungrouped plugin.\n\n".
                       "The --list option will display a list of available plugins and groups.";
            $this->error($message);
        }

        return $plugins;
    }

    /**
     * Generates a config.php file for Moodle.
     *
     * @param boolean $writetofile Whether or not to write it to dirroot.'/config.php'.
     * @param string $branch Optional git repo branch
     * @return mixed True or false if writing to a file, the contents of file otherwise
     */
    public function generate_config_file($writetofile = false, $branch = null) {
        $cfg = $this->get_config();
        $this->status('Generating config.php file');

        // Get the branch name.
        if ($branch == null) {
            $branch = $this->git->branch();
        }
        // Generate a salt if one doesn't exist.
        if (!(isset($cfg->passwordsaltmain) && strlen($cfg->passwordsaltmain) > 0)) {
            $cfg->passwordsaltmain = moodle_generate_salt();
        }
        // Set the dbtype.
        $cfg->dbtype = extension_loaded('mysqli') ? 'mysqli' : 'mysql';

        // Use the new project "Simple" config format if prep file exists.
        $simple = false;
        $pathext = '';
        $matches = array();
        if (file_exists($this->cfgprepfile) && preg_match('/^moodle_(prod|sand)(.*)$/', basename($cfg->dirroot), $matches)) {
            $pathext = $matches[2];
            $simple = true;
        }

        $lines = array();
        $lines[] = "<?php // config.php";

        $settings = array('wwwroot', 'dbpass', 'passwordsaltmain');
        if ($simple) {
            $lines[] = "require_once('/etc/php.d/config_prep.php');";
            $lines[] = '';
        } else {
            $lines[] = '// MOODLE CONFIGURATION FILE';
            $lines[] = '';
            $lines[] = 'unset($CFG);';
            $lines[] =  'global $CFG;';
            $lines[] =  '$CFG = new stdClass();';
            $lines[] = '';
            $settings = array('dbtype', 'dbhost', 'dbname', 'dbuser', 'dbpass');
        }

        foreach ($settings as $setting) {
            $lines[] =  "\$CFG->{$setting} = '".addslashes($cfg->$setting)."';";
        }

        if ($simple) {
            $lines[] = "// \$CFG->passwordsaltalt1 = '';";
            $lines[] = "\$CFG->rl_pathext          = '$pathext'; // incrementing digit if not the primary prod/sand site";
            $lines[] = "// \$CFG->loginhttps       = true;";
            if ($branch == 'MOODLE_19_STABLE') {
                $lines[] =  '$CFG->dirroot   = \''.$cfg->dirroot.'\'; // Can be removed when upgraded to M2.0+.';
            }
            $lines[] = '';
            $lines[] = "require_once('/etc/php.d/config_pre.php');";
            $lines[] = "require_once('/etc/php.d/config_'.\$CFG->rl_sitetype.'.php');";
            $lines[] = '';
            $lines[] = '/** CUSTOMER-SPECIFIC OVER-RIDES START **/';
            $lines[] = '// Log a SF change for each modification to this section';
            if ($cfg->dbhost != 'localhost') {
                $lines[] = "\$CFG->dbhost              = '{$cfg->dbhost}';";
            }
            $lines[] = '/** CUSTOMER-SPECIFIC OVER-RIDES END **/';
            $lines[] = '';
            $lines[] = "require_once('/etc/php.d/config_post.php');";
        } else {
            $lines[] =  '$CFG->prefix    = \'mdl_\';';
            $lines[] =  '$CFG->dbpersist = false;';
            $lines[] = '';

            $settings = array('wwwroot', 'dirroot', 'dataroot');
            foreach ($settings as $setting) {
                $lines[] =  "\$CFG->{$setting}    = '{$cfg->$setting}';";
            }

            if ($branch != 'MOODLE_19_STABLE') {
                $lines[] = '$CFG->themedir = $CFG->dataroot.\'/'.MOODLE_THEME_DIR.'\';';
            }
            $lines[] =  '$CFG->admin     = \'admin\';';
            $lines[] = '';

            $lines[] = '/* Performance Settings per RFC-910 */';
            $lines[] = '$CFG->cachejs           = true;';
            $lines[] = '$CFG->cachetext         = 60;';
            $lines[] = '$CFG->cachetype         = \'\';';
            $lines[] = '$CFG->curlcache         = 120;';
            $lines[] = '$CFG->dbsessions        = false;';
            $lines[] = '$CFG->langcache         = true;';
            $lines[] = '$CFG->langstringcache   = true;';
            $lines[] = '$CFG->rcache            = false;';
            $lines[] = '$CFG->slasharguments    = true;';
            $lines[] = '$CFG->yuicomboloading   = true;';
            $lines[] = '';

            $lines[] = '/* Security Settings per RFC-910 */';
            $lines[] = '$CFG->cookiehttponly     = true;';
            $lines[] = '$CFG->cookiesecure = '.(preg_match('|^https://|', $cfg->wwwroot) ? 'true' : 'false').';';
            /* On hold for now, as the presence of this var has caused problems in testing */
            /* $lines[] = '$CFG->loginhttps         = \'false\';'; */
            $lines[] = '$CFG->regenloginsession  = true;';
            $lines[] = '';

            $lines[] = '/* Debugging per RFC-910 */';
            $lines[] = '/* none: 0, minimal: 5, normal: 15, all: 6143, developer: 38911 */';
            $lines[] = '$CFG->debug        = 0;';
            $lines[] = '$CFG->debugdisplay = false;';
            $lines[] = '';

            $lines[] = '/* Executable locations per RFC-910 */';
            $lines[] = '$CFG->aspellpath             = \'/usr/bin/aspell\';';
            $lines[] = '$CFG->filter_tex_pathconvert = \'/usr/bin/convert\';';
            $lines[] = '$CFG->filter_tex_pathdvips   = \'/usr/bin/dvips\';';
            $lines[] = '$CFG->filter_tex_pathlatex   = \'/usr/bin/latex\';';
            $lines[] = '$CFG->pathtoclam             = \'/usr/bin/clamscan\';';
            $lines[] = '$CFG->pathtodu               = \'/usr/bin/du\';';
            $lines[] = '$CFG->pathtounzip            = \'/usr/bin/zip\';';
            $lines[] = '$CFG->pathtozip              = \'/usr/bin/zip\';';
            $lines[] = '';

            $lines[] = '/* RLIP paths per RFC-910 */';
            $lines[] = '$CFG->block_rlip_exportfilelocation      = \''.$cfg->dataroot.'/rlip/export/export.csv\';';
            $lines[] = '$CFG->block_rlip_filelocation      = \''.$cfg->dataroot.'/rlip/import\';';
            $lines[] = '$CFG->block_rlip_logfilelocation       = \''.$cfg->dataroot.'/rlip/log\';';
            $lines[] = '';

            /* On hold for now to resolve compatibility issues with M2 data directory */
            //$lines[] = '/* Extra theme directory per RFC-351 */';
            //$lines[] = '$CFG->themedir = \''.$CFG->dataroot.'/theme\';';

            $lines[] = '$CFG->passwordsaltmain = \''.$cfg->passwordsaltmain.'\';';
            $lines[] = '';

            $lines[] =  '$CFG->directorypermissions = 0770;';
            $lines[] = '';

            $lines[] =  '$CFG->disablescheduledbackups = true;';
            $lines[] =  '$CFG->disableupdatenotifications = true;';
            $lines[] =  '$CFG->enablestats = false;';
            $lines[] = '';

            $lines[] =  'require_once $CFG->dirroot.\'/lib/setup.php\';';
        }

        $lines[] = '';

        $config = implode("\n", $lines);

        if ($writetofile) {
            if (!file_put_contents($cfg->dirroot.'/config.php', $config)) {
                $this->error('Unable to write to file "'.$cfg->dirroot.'/config.php"');
            }
        }
        $this->success();

        return $config;

    }

    /**
     * Set the path of config prep file
     *
     * @param string $cfgprepfile The config prep file
     */
    public function set_config_prep_file($cfgprepfile) {
        $this->cfgprepfile = $cfgprepfile;
    }

    /**
     * Set the path for the plugin command files
     *
     * @param string $dir The command directory
     */
    public function set_plugin_command_dir($dir) {
        $this->plugincommanddir = $dir;
    }

    /**
     * Get Moodle addon groups from the Dashboard
     *
     * @return array An array of Dashboard addon groups
     */
    public function get_dashboard_addon_groups() {
        $identity = rlscripts_ws_identity();
        $request = array();
        $response = $this->com->send_request('get_moodle_plugin_groups', $request, $identity);
        try {
            $data = $this->com->decode_response($response);
        } catch (Exception $ex) {
            $this->warning($ex->getMessage());
        }

        if (array_key_exists('items', $data) && array_key_exists(0, $data['items'])) {
            return $data['items'][0];
        } else if (array_key_exists('error', $data)) {
            $this->error(__METHOD__.": {$data['error']}");
        } else {
            $this->error(__METHOD__.":\n    Unrecognized response from server:\n$response\n");
        }
    }

    /**
     * Get Moodle addons from the Dashboard
     *
     * @param int $level The level(s) of addons to return (bitmask)
     * @param int $branch The Moodle version (two-digit int like 27, 28, 29)
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return array An array of Dashboard addon objects
     */
    public function get_dashboard_addons($level, $branch = 0) {
        $cfg = $this->get_config();

        if (!(is_int($level) || is_string($level)) || (is_string($level) && !is_numeric($level))) {
            $this->error('get_dashboard_addons: Level must be a number ('.print_r($level, true).')');
        }

        if (!(is_int($branch) || is_string($branch)) || (is_string($branch) && !is_numeric($branch))) {
            $this->error('get_dashboard_addons: Branch must be a number ('.print_r($branch, true).')');
        }

        if (empty($branch)) {
            $this->prepare_git();
            $branch = $this->determine_moodle_branch_number($this->git->branch());
        }

        $identity = rlscripts_ws_identity();
        $request = array(
            'data' => array(
                'branchnum' => $branch,
                'level'     => $level,
                'private'   => 1,
            ),
        );

        $types = array();
        foreach ($this->plugintypes as $type) {
            $types[$type['type']] = $type['component'];
        }

        $addons = array();
        $response = $this->com->send_request('get_moodle_plugins', $request, $identity);
        try {
            $data = $this->com->decode_response($response);
        } catch (Exception $ex) {
            $this->warning($ex->getMessage());
        }
        if (!is_array($data) || !array_key_exists('items', $data) || !array_key_exists(0, $data['items'])) {
            $this->warning("get_dashboard_addons:\n    Unrecognized response from server:\n$response\n");
        } else if (array_key_exists('error', $data['items'][0])) {
            $this->warning("get_dashboard_addons: dashboard error: {$data['items'][0]['error']}");
        } else {
            foreach ($data['items'][0] as $name => $plugin) {
                if (!array_key_exists($plugin['type'], $this->plugintypes)) {
                    $this->warning("Unknown type ({$plugin['type']} sent by dashboard for plugin $name)");
                    continue;
                }
                $addon = new RLSCRIPTS_Moodle_Addon($this, $plugin['type'], $plugin['name']);
                $addon->description = $plugin['description'];
                $addon->displayname = $plugin['display_name'];
                $addon->groupingname = $plugin['groupingname'];
                if (!empty($plugin['path'])) {
                    $addon->path = $cfg->dirroot.'/'.$plugin['path'];
                }
                $addon->pathalt = $plugin['path_alt'];
                $addon->source = $plugin['source'];
                $addon->sourcealt = $plugin['source_alt'];
                $addon->moodleversions = $plugin['moodleversions'];
                $addon->rlversions = $plugin['rlversions'];
                $addon->rating = $plugin['rating'];
                $addons[$name] = $addon;
            }
        }

        return $addons;
    }

    /**
     * Get the hosting service from the dashboard for a salesforce account
     *
     * If the CLI is set, this function will also prompt the user to create a missing account.
     *
     * @param string $key The key for the salesforce account
     * @return int|bool Either the id of hosting service or false on error
     */
    public function get_hosting_service($key) {
        $service = 0;
        $request = array(
            'data' => array(
                'key' => $key,
            ),
        );
        $identity = rlscripts_ws_identity();

        $response = $this->com->send_request('get_hosting_service', $request, $identity);
        try {
            $data = $this->com->decode_response($response);
        } catch (Exception $ex) {
            $this->warning($ex->getMessage());
        }
        $services = array();

        if (is_array($data)) {
            $results = $data['items'];
            if (array_key_exists('result', $results[0]) && (strtolower(trim($results[0]['result'])) === 'ok')) {
                $services = $data['items'][0]['services'];
            }
            if (array_key_exists('error', $results[0])) {
                $this->warning("Dashboard returned error: {$results[0]['error']}");
            }
        }

        $service = 0;
        if (count($services) == 0) {
            $this->message('Unable to find a hosting service for this client.');
            $response = $this->prompt('Would you like to create a new hosting service for this client? (y/n)', '/^[yn]/i', 'no');
            if (substr($response, 0, 1) === 'y') {
                $response = $this->com->send_request('create_hosting_service', $request, $identity);
                try {
                    $data = $this->com->decode_response($response);
                } catch (Exception $ex) {
                    $this->warning($ex->getMessage());
                }

                if (is_array($data)) {
                    $results = $data['items'];
                    if (array_key_exists('result', $results[0]) && (trim(strtolower($results[0]['result'])) !== 'ok')) {
                        $this->warning('Unable to create hosting service');
                        if (array_key_exists('error', $results[0])) {
                            $this->warning("Dashboard returned error: {$results[0]['error']}");
                        }

                        return false;
                    }
                    $service = $results[0]['services'][0][0];
                }
            }
        } else if (count($services) > 1) {
            $this->message("\nMultiple hosting services found for client:");
            $list = array();
            foreach ($services as $row) {
                $list[$row[0]] = $row[1];
            }
            $item = new menuitem_list('', 'Please choose a hosting service:', $list);
            $item->prompt();
            $service = $item->get_value();
        } else {
            $service = $services[0][0];
        }

        return $service;
    }

    /**
     * Get the path to the incron file
     *
     * @return string The full path to the incron file
     */
    public function get_incron_path() {
        return $this->incron_path;
    }

    /**
     * Get Moodle branches
     *
     * @return string The log file as a string
     */
    public function get_moodle_branches() {
        $this->prepare_git();

        $filter = 'MOODLE_(\d+)_STABLE';

        return $this->git->branches($filter);
    }

    /**
     * Get the list of plugin types
     *
     * @return string The log file as a string
     */
    public function get_plugin_types() {
        return $this->plugintypes;
    }

    /**
     * Get moodle plugins
     *
     * @param int   $source  The source (disk or db)
     * @param array $types   The type of plugin to get
     * @param bool  $refresh Whether to force a refresh
     * @return array A list of plugin information
     */
    public function get_plugins($source = self::SOURCE_DISK, $types = array(), $refresh = false) {
        switch ($source) {
            case self::SOURCE_DB:
                $plugins = 'db_plugins';
                break;
            default:
                $plugins = 'disk_plugins';
                break;
        }
        $loader = "load_$plugins";

        if (empty($types)) {
            $types = array_keys($this->plugintypes);
        }

        if ($refresh || ($this->$plugins == null)){
            $this->$loader($types);
        }

        $list = array();
        foreach ($types as $type) {
            if (!array_key_exists($type, $this->$plugins)) {
                $this->$loader(array($type));
            }

            // Because $this->$plugins[$type] doesn't return what you would expect, use an alias
            $alias = &$this->$plugins;
            $list[$type] = $alias[$type];
        }
        return $list;
    }

    /**
     * Tells whether or not the specified path appears to be an ELIS site.
     *
     * @param string $path The path to check.
     *
     * @return bool True if it's a Moodle site, false otherwise
     */
    function is_elis($path = '') {

        if ($path == '') {
            $cfg  = $this->get_config();
            $path = $cfg->dirroot;
        }

        $program_dir = $path .'/elis/program';

        $moodle      = $this->is_moodle($path);
        $elis        = $moodle && is_dir($program_dir);

        return $elis;
    }

    /**
     * Tells whether or not the specified path appears to be an GAO site.
     *
     * @param string $path The path to check.
     *
     * @return bool True if it's a Moodle site, false otherwise
     */
    function is_gao($path = '') {
        $gao = false;

        if ($path == '') {
            $cfg  = $this->get_config();
            $path = $cfg->dirroot;
        }

        // TODO: Replace this if statement with one call to $this->prepare_git($path)
        if ($path == $cfg->dirroot) {
            $this->prepare_git();
            $git = $this->git;
        } else {
            $git = new RLSCRIPTS_Git($path);
        }

        $submodule = false;
        $submodules = $git->submodule(true);

        if (sizeof($submodules) == 0) {
            $gao = true;
        }

        return $gao;
    }

    /**
     * Log history to the Dashboard
     *
     * @param string $action  The action taken
     * @param string $message The message to record.
     */
    function history($action, $message) {
        $config = $this->get_config();

        $moodlerelease = "Unavailable";
        if (!empty($config->release)) {
            $moodlerelease = $config->release;
        }

        $elisrelease = "Unavailable";
        if (!empty($config->rlscripts_elis_release)) {
            $elisrelease = $config->rlscripts_elis_release;
        }

        $history = array(
            'data' => array(
                'action'        => $action,
                'dirroot'       => $config->dirroot,
                'url'           => base64_encode($config->wwwroot),
                'message'       => $message,
                'moodle_version'=> $moodlerelease,
                'elis_version'  => $elisrelease
            ),
        );

        $response = $this->com->send_request('store_history', $history, rlscripts_ws_identity());
        try {
            $data = $this->com->decode_response($response);
        } catch (Exception $ex) {
            $this->warning($ex->getMessage());
        }
        $msg = 'Web services returned message in an invalid format';

        /*
         * In the future an array of error messages may need to be returned in which case
         * this can be modified accordiangly. For now only one message is expected.
         */
        if (is_array($data) && isset($data['items'][0])) {
            $message = $data['items'][0];
            if (isset($message['result']) && $message['result'] === 'OK') {
                $msg = '';
            }
            if (isset($message['message'])) {
                $msg = $message['message'];
            }
            if (isset($message['Error'])) {
                $msg = 'Dashboard error: '.$message['Error']."\n";
            }
        }

        $this->message($msg);
    }

    /**
     * Tells whether or not the moodle site is web accessible
     *
     * @return bool True if it's accessible, otherwise false
     */
    function is_accessible() {
        $cfg        = $this->get_config();
        $accessible = is_web_accessible($cfg->wwwroot, $cfg->dirroot);

        return $accessible;
    }

    /**
     * Tells whether or not the specified path appears to be a Moodle site.
     *
     * @param string $path The path to check.
     *
     * @return bool True if it's a Moodle site, false otherwise
     */
    function is_moodle($path = '') {
        return $this->is_valid($path);
    }

    /**
     * Load plugins
     *
     * @param array $types Not currently used
     * @return array A list of plugin information
     */
    public function load_db_plugins($types = array()) {
        $cfg = $this->get_config();
        $helper = 'moodle_get_plugins';
        $options = "'{$cfg->dirroot}'";
        if (!$this->call_helper($helper, $options)) {
            $this->error('Unable to load plugins from Moodle database');
            return array();
        }

        $list = array();
        $lines = $this->shell->stdoutArray();
        $line = array_shift($lines);
        $cols = explode("\t", $line);

        // Trim the names in case extra spaces crept in from bad Moodle config files.
        foreach ($cols as $key => $name) {
            $cols[$key] = trim($name);
        }

        $names = array();
        foreach ($this->plugintypes as $type) {
            $list[$type['component']] = array();
            $names[$type['type']] = $type['component'];
        }

        foreach ($lines as $line) {
            $row    = explode("\t", $line);
            $data = array();

            foreach ($cols as $key => $name) {
                $data[$name] = trim($row[$key]);
            }

            if (!array_key_exists($data['type'], $names)) {
                $this->warning("Unkown plugin type: {$data['type']}.  Skipping {$data['type']}_{$data['name']}.");
                if (DEBUG) {
                    $this->message("Line: '$line'");
                }
                continue;
            }
            $plugin = new RLSCRIPTS_Moodle_Addon($this, $names[$data['type']], $data['name']);

            // Fill out the missing version information.
            $plugin->instances = $data['instances'];

            $releases = $plugin->releases;
            $releases['disk'] = $data['release'];
            $plugin->releases = $releases;

            $plugin->ondisk = intval($data['ondisk']);

            $versions = $plugin->versions;
            $versions['database'] = intval($data['version_db']);
            $versions['disk'] = intval($data['version_disk']);
            $plugin->versions = $versions;

            $plugin->visible = intval($data['visible']);

            // Fill out the missing dependency information.
            $dependencies = $plugin->dependencies;
            $deps = explode(',', $data['dependencies']);
            foreach ($deps as $dep) {
                if (!empty($dep)) {
                    $parts = explode(':', $dep);
                    if (count($parts) != 2) {
                        $this->warning("Malformed dependency ($dep) for {$data['name']}");
                    }
                    $dependencies['disk'][$parts[0]] = $parts[1];
                }
            }
            $plugin->dependencies = $dependencies;
            $list[$names[$data['type']]][$data['name']] = $plugin;

        }

        $this->db_plugins = $list;
    }

    /**
     * Load plugins from disk
     *
     * @param array $types The type of plugin to get
     * @return array A list of all the plugins that have code in the specified plugin directories
     */
    public function load_disk_plugins($types = array()) {
        $cfg  = $this->get_config();

        if (!is_array($types)) {
            $types = array($types);
        }

        foreach ($types as $type) {
            $this->disk_plugins[$type] = array();

            if (!array_key_exists($type, $this->plugintypes)) {
                $this->warning("Unknown plugin type: $type");
                continue;
            }
            $root = $cfg->dirroot.'/'.$this->plugintypes[$type]['path'];

            if (!is_dir($root)) {
                $this->warning("Plugin directory is not a directory: $root");
                continue;
            }

            if (($handle = opendir($root)) === false) {
                $this->warning("Failed to open plugin directory: $root");
                continue;
            }

            while (($dir = readdir($handle)) !== false) {
                if (($dir == '.') || ($dir == '..') || !is_dir($root.'/'.$dir)) {
                    continue;
                }

                $file = str_replace('$1', $dir, $this->plugintypes[$type]['file']);
                $path = $root.'/'.$dir.'/'.$file;

                if (file_exists($path)) {
                    $this->disk_plugins[$type][$dir] = $dir;
                }
            }
        }
    }

    /**
     * Migrate a Moodle git repository to the GAO repository
     *
     * @param string $branch The branch to use for GAO
     */
    public function migrate_to_gao($branch = null) {
        $this->set_log_file('migration.log');
        $cfg = $this->get_config();
        $this->prepare_git();

        $repository  = $this->git->repository();
        $webroot     = $cfg->dirroot;

        if ($branch == null) {
            $branch      = $this->git->branch();
        }

        $result = $this->disable_filemode_tracking();
        if ($result === false) {
            $this->warning('Failed disabling filemode tracking');
        }

        $repository = basename($repository, '.git');

        if ($cfg->dataroot == '') {
            $this->error("ERROR: moodledata is not set");
        } else {
            $moodledata_dir = rtrim($cfg->dataroot,'/');
        }
        $migration_dir = 'submodule-migration-backup';
        $backup_dir    = $moodledata_dir.'/'.$migration_dir;
        $this->logfile = $backup_dir .'/migration.log';

        // Display info
        $this->message("\n". str_pad('Processing:', 25) . $webroot);
        $this->message(str_pad('Migration logs:',  25)  . $backup_dir);
        $this->message("\n". str_pad('Old repository branch:',  25)  . $branch);
        $this->message(str_pad('Old origin repository:', 25) . $repository);

        if ($repository == 'rlmoodle.elis') {
            $new_repository = 'rlmoodle.elis.git';

        } else if (($repository == 'rlmoodle.plain') || ($repository == 'moodle')) {
            $new_repository = 'rlmoodle.gao.git';

        } else {
            $new_repostiory = $repository;
        }

        $matches = array();

        if ($this->determine_moodle_branch_number($branch) < 21) {
            $branch = 'MOODLE_21_STABLE';
        }

        $this->message(str_pad('New origin repository:', 25) . $new_repository ."\n");

        $pattern = '/('. implode("|", $this->get_branches($new_repository, 21)) .'|)/';
        $new_branch = $this->prompt("Specify GAO branch to use (press enter for {$branch}):", $pattern, $branch);

        $this->message("New origin branch: {$new_branch}\n\n", true);

        // Give option to abort at the start
        $this->message('It is recommended that you make a backup of the directory before proceeding,'
                      ." please do so now before proceeding.\n");
        $this->prompt_continue("If you are ready to proceed, enter 'y' to continue: ");

        $this->status("\nCreating Migration Backup Direcory");
        // Make a migration backup directory
        @mkdir($backup_dir);

        if (!file_exists($backup_dir)) {
            $this->failure();
            $this->error("ERROR: could not create backup directory {$backup_dir}\n");
        }
        $this->success();

        $this->shell->exec("date >> $backup_dir/migration.log");

        // Take a snapshot of git status before any git clean-up
        $result = $this->git->exec('status', "> $backup_dir/git-status-initial.txt");
        if ($result === false) {
            $this->warning('Failed taking a snapshot of git status');
        }

        $warnings = array();

        // Give option to clean up before attempting migration to rlmoodle.gao
        if ($new_repository == 'rlmoodle.gao.git') {
            $this->message("\nMigration to rlmoodle.gao may not work if there are conflicts in the current install.\n");
            $clean = $this->prompt("Enter 'y' to perform git clean-up before submodule migration:", '//', '');

            if (strtolower($clean) == 'y') {
                $this->status("\nCleaning repository");
                $this->log($this->git->cleanup());
                $this->success();
            }
        }

        $this->message("Checking submodules...\n");

        // Generate a list of submodules that require migration for this installation
        $submodules         = $this->git->submodule();
        $current_submodules = array();

        $submodule_data = submodules::get();

        foreach ($submodules as $submodule) {
            $current_submodules[$submodule->path()] = 1;
        }

        // Check for other gao plugins that are present but not as a submodule
        foreach ($submodule_data as $check_submodule) {
            $check_path     = $check_submodule['path'];
            $submodule_path = $webroot .'/'. $check_path;

            if (file_exists($submodule_path)) {

                if (array_key_exists($check_path, $current_submodules)) {
                    $git = new RLSCRIPTS_Git($submodule_path);
                    // Check for clean submodule
                    $status = $git->checkStatus();
                    if ($status === false) {
                        $this->warning('Git status check failed');
                    }
                    if (!$status['clean']) {
                        $warnings[] = "WARNING: submodule at {$check_path} contains possible customizations; creating backup";
                        $tar_name = str_replace('/','_',$check_path);
                        $git->exec('status', '> '. $backup_dir .'/git-status-'. $tar_name .'.txt');
                        $action = 'tar -czvf '.$backup_dir .'/'. $tar_name .'.tgz '. $submodule_path;
                        $this->shell->exec($action);
                    }
                } else {
                    // Check existing code against contents of rlmoodle.gao
                    $warnings[] = "WARNING: conflicting plugin at {$check_path}; creating backup";
                    $tar_name = str_replace('/','_',$check_path);
                    $action = 'tar -czvf '. $backup_dir .'/'. $tar_name .'.tgz '. $submodule_path;
                    $this->shell->exec($action);
                }
            }
        }

        // Prompt the user whether to continue with migration
        if (count($warnings) > 0) {
            foreach ($warnings as $warning) {
                $this->message($warning ."\n");
                $this->log($warning);
            }
            $this->prompt_continue("\nEnter 'y' to proceed with migration (unknown submodules will"
                                .' not be migrated and all conflicts and customizations will be overwritten): ');
        }

        $this->message("\nMigrating...\n\n");

        $prefix = strlen($webroot) +1;

        // Remove submodules
        $this->remove_submodules();

        $directories = array();

        // Remove any possible remaining conflicts
        foreach ($submodule_data as $submodule_name=>$current_submodule) {
            $directories[] = $current_submodule['path'];
        }
        $this->directory_delete($directories);

        if ($this->update_git($new_branch, $new_repository)) {
            $this->update_database();
        }

        $this->log("Done.\n");
        $this->write_log();

        $this->message("Done - see log in {$backup_dir}/migration.log\n");
    }

    /**
     * Remove all the submodules from the Moodle repository
     */
    public function remove_submodules() {
        $cfg = $this->get_config();
        // Remove submodules
        $this->log($this->git->remove_submodules());

        $actions = array();

        // Commit the fact that we removed submodules so we can pull
        if (file_exists($cfg->dirroot .'/.gitmodules')) {
            $actions[] =  array('cmd' => 'add', 'args' => '.gitmodules', 'status' => '');
        }
        $actions[] = array(
            'cmd' => 'commit',
            'args' => ' -m "Removed submodules"',
            'status' => 'Committing submodule removal'
        );

        $this->process_git($actions);
    }

    /**
     * Run the ELIS upgrade scripts
     *
     * This function will invoke an external script to isolate the upgrade from any
     * potential failures or drift in the Moodle code.
     *
     * @return boolean True for success, false for failure
     */
    public function run_preupgrade_script() {
        $cfg = $this->get_config();

        $this->status('Running Remote Learner pre-upgrade script');

        $script = $cfg->dirroot .'/elis/core/scripts/preupgrade.php';

        if (file_exists($script)) {
            parent::call_helper($script, array(), 'Pre-upgrade script failed!', array($this, '_database_helper_callback'));
            $this->shell->reset();
        }
    }

    /**
     * Run the Moodle CLI upgrade script
     *
     * This function will invoke an external script to isolate the upgrade from any
     * potential failures or drift in the Moodle code.
     *
     * @return boolean True for success, false for failure
     */
    public function run_upgrade_script() {
        $cfg = $this->get_config();
        $script = "php {$cfg->dirroot}/admin/cli/upgrade.php";
        // According to CLI upgrade.php script: allow-unstable is required by non-interactive
        $options = '--non-interactive --allow-unstable';

        return parent::call_helper($script, $options, 'Moodle CLI upgrade failed');
    }

    /**
     * Remove the block.
     *
     * @param string $type The type field can be used to force block removal
     * @return bool True on success
     */
    public function unblock($type = '') {
        $success = false;
        $config = $this->get_config();

        $block = $type;
        if ($block == '') {
            $block = $this->blocked;
        }

        if ($block == 'maintenance') {
            $this->status('Taking site out of maintenance mode');
            // Because we do not know which version of Moodle the block was enabled on, check both.
            $files = array(static::MAINT_FILE_MODERN, static::MAINT_FILE_LEGACY);
            foreach ($files as $file) {
                $path = $config->dataroot.'/'.$file;
                if (file_exists($path)) {
                    $success |= unlink($path);
                }
            }

        } else if ($block == 'htaccess') {
            $this->status('Removing site blocking .htaccess file');
            $success = $this->webserver->remove_htaccess_rules($config->dirroot);
        }

        if ($block == $this->blocked) {
            $this->blocked = '';
        }

        return $this->result($success);
    }

    /**
     * Update the Moodle site
     *
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return boolean True if the update was successfully completed, false if it failed
     */
    public function update($sandboxdirroot = '') {
        $this->prepare_git();

        if (!$this->update_git()) {
            return false;
        }

        $branch = $this->git->branch();
        $number = $this->determine_moodle_branch_number($branch);
        if ($number >= 27) {
            $installed = $this->get_plugins(static::SOURCE_DB);
            if (!$this->addon_add_missing($installed, $number, $sandboxdirroot)) {
                return false;
            }
            // Remove any plugins with missing dependencies
            $this->addon_remove_missing_dependencies($installed, $number, $sandboxdirroot);
        }
        return $this->update_database();
    }

    /**
     * Update the config.php for Moodle.
     *
     * @param array $cfgs An array of key-value pairs for config.php
     * @param boolean $write_to_file Whether or not to write it to $CFG->dirroot.'/config.php'.
     *
     * @return mixed True or false if writing to a file, the contents of file otherwise
     */
    public function update_config_file($cfgs, $write_to_file=false) {

        $handle = fopen($this->cfg->dirroot .'/config.php', 'r');

        if (!$handle) {
            $this->error('Could not open '. $this->cfg->dirroot .'/config.php for updates.');
        }

        $lines = array();
        $pos   = 0;
        $found = false;
        while (($line = fgets($handle)) !== false) {
            $lines[] = rtrim($line);

            if (false !== strpos($line, '$CFG->dirroot/lib/setup.php')) {
                $found = true;
            }

            if (!$found) {
                $pos += 1;
            }
        }

        foreach ($cfgs as $config => $val) {
            if (!isset($this->cfg->{$config})) {
                $start = array_slice($lines, 0, $pos);
                $end   = array_slice($lines, $pos);

                array_unshift($end, $val);
                array_unshift($end, '');

                $lines = array_merge($start, $end);
            }
        }

        $config = implode("\n", $lines);

        if ($write_to_file) {
            if (!file_put_contents($this->cfg->dirroot.'/config.php', $config)) {
                $this->error('Unable to write to file "'.$this->cfg->dirroot.'/config.php"');
            }
        }
        $this->success();

        return $config;
    }

    /**
     * Run Moodle's database upgrade scripts
     *
     * This function will invoke an external script to isolate the upgrade process from any
     * potential failures or drift in the Moodle code.
     *
     * @return boolean True for success, false for failure
     */
    public function update_database() {
        $cfg    = $this->get_config();
        $branch = $this->git->branch();

        if (preg_match('/MOODLE_(\d+)_STABLE/', $branch, $matches) && ($matches[1] >= 20)) {
            $this->status('Updating Moodle database');

            $this->call_helper('moodle2_upgrade', "--verbose '{$cfg->dirroot}'", 'Moodle database setup failed!', array($this, '_database_helper_callback'));
            $this->shell->reset();
        }
        return true;
    }

    /**
     * Upgrade the Moodle site
     *
     * @param string $branch     The branch to use (blank for current)
     * @param string $repository The foreign repository to use as the new origin (blank for current)
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return boolean True if the update was successfully completed, false if it failed
     */
    public function upgrade($branch = '', $repository = '', $sandboxdirroot = '') {
        $this->prepare_git();
        $success = false;
        $old['branch'] = $this->git->branch();
        $old['repository'] = basename($this->git->repository());

        $new['branch'] = $branch;
        if ($new['branch'] == '') {
            $new['branch'] = $old['branch'];
        }
        $new['repository'] = basename($repository);
        if ($new['repository'] == '') {
            $new['repository'] = $old['repository'];
        }

        $result = $this->disable_filemode_tracking();
        if ($result === false) {
            $this->warning('Failed disabling filemode tracking');
        }

        $matches = array();

        $old['number'] = $this->determine_moodle_branch_number($old['branch']);
        $new['number'] = $this->determine_moodle_branch_number($new['branch']);

        $this->log("Upgrading from {$old['number']} to {$new['number']}");

        $oldisgao = ($old['repository'] == 'rlmoodle.gao.git');
        $oldis21elis = (($old['repository'] == 'rlmoodle.elis.git') && ($old['number'] >= 21));
        $oldis21elisfiles = (($old['repository'] == 'rlmoodle.elisfiles.git') && ($old['number'] >= 21));
        $newisgao = ($new['repository'] == 'rlmoodle.gao.git');
        $newis21elis = (($new['repository'] == 'rlmoodle.elis.git') && ($new['number'] >= 21));
        $newis21elisfiles = (($new['repository'] == 'rlmoodle.elisfiles.git') && ($new['number'] >= 21));
        $old['gao'] = ($oldisgao || $oldis21elis || $oldis21elisfiles);
        $new['gao'] = ($newisgao || $newis21elis || $newis21elisfiles);

        $submodules = $this->git->submodule(true);
        // No submodules allowed after M20
        $remove = ($newisgao || ($new['version'] >= 21));

        // Remove submodules if present and they need to be removed.
        if ((sizeof($submodules) > 0) && $remove) {
            $this->remove_submodules();
        }

        // Remove gao plugin directories if they should not be there and we need to add them.
        if (!$old['gao'] && $new['gao']) {
            $this->delete_gao_plugins();
        }

        // Delete unknown plugins before changing branch/repository to prevent blocking files
        if (($old['number'] <= 19) && ($new['number'] >= 20)) {
            $this->delete_unknown_plugins();
        }

        // As of Moodle 2.7.3 no additional plugins are included in the base repository
        // Delete all non-standard plugins to prevent API changes from breaking the upgrade.
        // We'll restore all available plugins after the core database has been upgraded.
        if ($old['number'] >= 27) {
            $this->delete_nonstandard_plugins();
        }

        if ($this->update_git($branch, $repository)) {
            // For Moodle 2.0 Upgrade
            if (($old['number'] <= 19) && ($new['number'] >= 20)) {
                $this->run_preupgrade_script();
            }
            // The addon wrangling must occur after the core database is upgraded.
            $success = $this->update_database();

            if (($old['number'] < 27) && ($new['number'] >= 27)) {
                $this->create_incron_entry();
            }

            $installed = array();
            if ($new['number'] >= 27) {
                // If we are going to M27 or later we need a list of installed plugins.
                $installed = $this->get_plugins(static::SOURCE_DB);

                // Install any missing plugins if we're at M27 or higher.
                $this->addon_add_missing($installed, $new['number'], $sandboxdirroot);

                $required = array('auth' => array('rladmin'), 'block' => array('rlagent'));
                foreach ($required as $type => $list) {
                    foreach ($list as $name) {
                        if (!array_key_exists($name, $installed[$type])) {
                            $addon = new RLSCRIPTS_Moodle_Addon($this, $type, $name);
                            $addon->set_sandboxpath($sandboxdirroot);
                            $addon->add();
                        }
                    }
                }
                // Force a reload of the plugin data so that it is up to date.
                $installed = $this->get_plugins(static::SOURCE_DB, array(), true);
                // Remove any plugins with missing dependencies
                $this->addon_remove_missing_dependencies($installed, $new['number'], $sandboxdirroot);
                // Run the database upgrade again to handle plugin updates.
                $success = $this->update_database();
            }
        }

        return $success;
    }

    /**
     * Upgrade the Moodle site to use git
     */
    public function upgrade_to_git($tmpdir) {
        $cfg        = $this->get_config();
        $repository = 'ssh://rlgit-auto/rlmoodle.plain.git';
        $branch     = 'MOODLE_19_STABLE'; // Only Moodle 1.9 sites should not have git.

        $this->cli->status('Upgrading to Git');

        // TODO: Use $this->prepare_git instead
        $this->git = RLSCRIPTS_Git::newFromClone($repository, $tmpdir, $branch);

        $this->cli->status('Copying extra files');
        $it = new directory_iterator($tmpdir);
        for ($count = 0; $it->valid(); ++$count, $it->next()) {
            $subdir = $it->sub();
            if (file_exists($cfg->dirroot.'/'.$subdir)
                || preg_match('/(\/|^)CVS(\/|$)/', $subdir)
                || preg_match('/(\/|^)CVSROOT(\/|$)/', $subdir)
                || basename($cfg->dirroot.'/'.$subdir) == 'error_log'
                || basename($cfg->dirroot.'/'.$subdir) == 'DEADJOE'
                || preg_match('/(\/|^)cgi\-bin(\/|$)/', $subdir)
                || substr($subdir, -1) == '~'
                || substr($subdir, -9) == '.php.orig'
                || $subdir == 'latest.gz'
                || $subdir == 'config.php'
            ) {
                continue;
            }

            if (is_dir($it->item())) {
                if (mkdir($cfg->dirroot.'/'.$subdir, 0750, true) === false) {
                    $this->error("Failed to create directory \"{$cfg->dirroot}/$subdir\"");
                }
            } else if (copy($it->item(), $cfg->dirroot.'/'.$subdir) === false) {
                $this->error('Failed to copy file "'.$it->item().'"');
            }
        }//end for

        // TODO: Use $this->prepare_git instead.
        $this->git = new RLSCRIPTS_Git($cfg->dirroot);
        $submodules = submodules::get();
        foreach ($submodules as $mod) {
            $path = $mod['path'];
            if (is_dir($cfg->dirroot . '/' . $path)) {
                if (recursively_remove_directory($cfg->dirroot . '/' . $path) === false) {
                    $this->error('Failed to remove "'.$path.'"');
                }
            }
        }

        $this->git->submodule()->update();
        $this->git->config('core.filemode', false);
        $this->git->submodule()->config('core.filemode', false);

        if (recursively_remove_directory($tmpdir) === false) {
           $this->error('Failed to remove "'.$tmpdir.'"');
        }

        $this->cli->success();
        return true;
    }

    /**
     * Validate the database
     *
     * @param string $path The path to the Moodle site
     */
    public function validate_db($path = '') {
        global $DIR_RLSCRIPTS;

        if ($path == '') {
            $cfg  = $this->get_config();
            $path = $cfg->dirroot;
        }

        $lines   = array();
        $success = false;
        $path    = $this->shell->get_absolute_path($path);
        $options = '';

        if ($path !== false && $this->is_moodle($path)) {

            $this->message('');
            $this->message('Checking current database status:');
            $cmd = "$DIR_RLSCRIPTS/moodle/helper/moodle_db_validator '$path'";
            $result = $this->shell->exec("su {$this->scriptuser} -s /bin/bash -c \"$cmd\"");

            foreach ($this->shell->stdoutArray() as $line) {
                $this->message('    '.$line);
            }
        }
    }

    /**
     * Enable specified GAO+ plugin
     *
     * @param string $moodleversion A two digit Moodle version identifier
     * @param string $gaoplusname GAO+ plugin grouping name
     * @param bool $ignoreerrors Flag to not stop execution of script on error
     */
    public function gaoplus_enable($moodleversion, $gaoplusname, $ignoreerrors) {
        global $_RLSCRIPTS;
        $cfg = $this->get_config();
        $enabledtypes = array();
        $enabledplugins = $this->gaoplus_fetch_enabled();

        $plugins = $this->gaoplus_get_plugin_list($moodleversion, $gaoplusname);

        foreach ($plugins as $path => $data) {
            $normalpath = $cfg->dirroot.'/'.$path;
            $hiddenpath = substr_replace($normalpath, '/.', strrpos($normalpath, '/'), 1);
            $hiddengaobase = '.'.basename($path);
            if (file_exists($normalpath)) {
                $this->message("{$path} is already enabled!\n");
                continue;
            } else if (file_exists($hiddenpath)) {
                $linkgaoplus = 'cd "'.$cfg->dirroot.'"; ln -s "'.$hiddengaobase.'" "'.$path.'"';
                if ($this->shell->exec($linkgaoplus) !== 0) {
                    $errmessage = "Could not create symlink {$normalpath}!";
                    if ($ignoreerrors) {
                        $this->warning($errmessage);
                    } else {
                        $this->error($errmessage);
                    }
                }
                if (!in_array($data['type'], $enabledtypes)) {
                    $enabledtypes[] = $data['type'];
                }
            } else {
                $errmessage = "Missing hidden directory $hiddenpath for {$gaoplusname}!";
                if ($ignoreerrors) {
                    $this->warning($errmessage);
                } else {
                    $this->error($errmessage);
                }
            }
            $this->message("Plugin {$path} has been enabled.\n");
        }

        // Save GAO+ grouping name in Moodle database.
        if (!in_array($gaoplusname, $enabledplugins)) {
            $enabledplugins[] = $gaoplusname;
            $this->gaoplus_save_enabled($enabledplugins);
        }

        // Run the Moodle install helper script for each plugin type we just enabled.
        if (!empty($enabledtypes)) {
            foreach ($enabledtypes as $gaotype) {
                $addon = new RLSCRIPTS_Moodle_Addon($this, $gaotype, 'all');
                $addon->install();
            }
        }
    }

    /**
     * Disable specified GAO+ plugin
     *
     * @param string $moodleversion A two digit Moodle version identifier
     * @param string $gaoplusname GAO+ plugin grouping name
     * @param bool $ignoreerrors Flag to not stop execution of script on error
     */
    public function gaoplus_disable($moodleversion, $gaoplusname, $ignoreerrors) {
        $cfg = $this->get_config();
        $pluginarray = $this->gaoplus_directory_status($moodleversion);
        $enabledplugins = $this->gaoplus_fetch_enabled();

        // Make sure they really want to do this.
        $response = $this->prompt("Are you sure you want to uninstall and disable {$gaoplusname}? (y/n)", '/^[yn]/i', 'yes');
        if (strtolower(substr($response, 0, 1)) != 'y') {
            $this->message("Procedure aborted by user.");
            return;
        }
        $this->message('');

        $plugins = $this->gaoplus_get_plugin_list($moodleversion, $gaoplusname);

        // Run the Moodle uninstall helper script for each component.
        foreach ($plugins as $data) {
            $addon = new RLSCRIPTS_Moodle_Addon($this, $data['type'], $data['name']);
            $addon->uninstall();
        }

        // Remove symlinks for all plugin components for the specified GAO+ grouping name.
        foreach ($plugins as $path => $data) {
            $normalpath = $cfg->dirroot.'/'.$path;
            $hiddenpath = substr_replace($normalpath, '/.', strrpos($normalpath, '/'), 1);

            if (file_exists($normalpath)) {
                $rmgaoplus = 'rm "'.$normalpath.'"';
                if ($this->shell->exec($rmgaoplus) !== 0) {
                    $errmessage = "Could not remove symlink {$path}!";
                    if ($ignoreerrors) {
                        $this->warning($errmessage);
                    } else {
                        $this->error($errmessage);
                    }
                }
                if (!file_exists($hiddenpath)) {
                    $errmessage = "Missing hidden directory {$path}!";
                    if ($ignoreerrors) {
                        $this->warning($errmessage);
                    } else {
                        $this->error($errmessage);
                    }
                }
            } else {
                $this->message("{$path} is already disabled!");
            }

            $this->message("Plugin {$path} has been disabled.");
        }

        // Remove GAO+ grouping name from Moodle database.
        if (in_array($gaoplusname, $enabledplugins)) {
            $newenabledplugins = array();
            foreach ($enabledplugins as $gaoplusitem) {
                if (!empty($gaoplusitem) && $gaoplusitem != $gaoplusname) {
                    $newenabledplugins[] = $gaoplusitem;
                }
            }
            $this->gaoplus_save_enabled($newenabledplugins);
        }
    }

    /**
     * Display status for specified GAO+ plugin
     *
     * @param string $moodleversion A two digit Moodle version identifier
     * @param string $gaoplusname GAO+ plugin grouping name
     */
    public function gaoplus_status($moodleversion, $gaoplusname) {
        $componentcount = 0;
        $enabledcount = 0;
        $disablecount = 0;
        $missingcount = 0;

        $plugins = $this->gaoplus_get_plugin_list($moodleversion, $gaoplusname);

        echo 'Plugin: '.$gaoplusname."\n\n";

        foreach ($plugins as $gaopath => $data) {
            echo $gaopath;

            $componentcount++;
            if ($data['status'] == 'enabled') {
                $enabledcount++;
                echo " directory is enabled.\n";
            } else if ($data['status'] == 'disabled') {
                $disabledcount++;
                echo " directory is disabled.\n";
            } else {
                $missingcount++;
                echo " directory is missing!\n";
            }
        }

        echo "\n";

        // Check for mismatching status on components.
        if ($enabledcount > 0 && $disabledcount > 0) {
            echo "WARNING: Some components of {$gaoplusname} are disabled!\n";
            echo "RECOMMENDATION: Either fully disable or enable all components.\n";
        } else if ($enabledcount > 0) {
            echo "SUMMARY: {$gaoplusname} is enabled (visible to Moodle)\n";
        } else if ($disabledcount > 0) {
            echo "SUMMARY: {$gaoplusname} is disabled (not visible to Moodle)\n";
        }

        // Check for missing components.
        if ($missingcount > 0) {
            if ($missingcount == $componentcount) {
                echo "WARNING: All of the code for {$gaoplusname} is missing!\n";
                echo "RECOMMENDATION: Update to GAO/GAO+ codebase.\n";
            } else {
                echo "WARNING: Some of the code for {$gaoplusname} is missing!\n";
                echo "RECOMMENDATION: Update the GAO/GAO+ codebase.\n";
            }
        }
    }

    /**
     * Display all supported GAO+ plugins along with their current directory status
     *
     * @param string $moodleversion A two digit Moodle version identifier
     */
    public function gaoplus_list($moodleversion) {
        $pluginarray = $this->gaoplus_directory_status($moodleversion);

        foreach ($pluginarray as $group => $pluginset) {
            if ($group != '') {
                print("Group: $group\n");
            } else {
                print("Plugins:\n");
            }

            foreach ($pluginset as $gaopath => $data) {
                $paddingspaces = 60 - strlen($gaopath);
                if ($data['status'] == 'enabled') {
                    $status = '[ ENABLED ]';
                } else if ($data['status'] == 'disabled') {
                    $status = '[ ------- ]';
                } else {
                    $status = '[ MISSING ]';
                }
                echo $gaopath.str_repeat(' ',$paddingspaces).$status."\n";
            }

            echo "\n";
        }
    }

    /**
     * Generate a list of all GAO+ plugins along with their current directory status
     *
     * @param string $moodleversion A two digit Moodle version identifier
     * @param string $gaoplusname Optional GAO+ grouping name
     */
    private function gaoplus_directory_status($moodleversion, $gaoplusname = '') {
        // We do not support GAO+ plugins on older versions of Moodle.
        if ($moodleversion <= 20) {
            $this->error("This script does not support GAO+ plugins in this version of Moodle.");
        } else if ($moodleversion >= 27) {
            $this->error("Please use the MASS system instead.");
        }

        $cfg = $this->get_config();
        $plugins = new RLSCRIPTS_Moodle_Plugins($this->com);
        $gaoplusarray = $plugins->get_list_path($moodleversion, MOODLELIB_SOURCE_GAOPLUS, $gaoplusname);
        $pluginarray = array();

        if (empty($gaoplusarray)) {
            $this->warning("No matching GAO+ plugins found.");
        }

        foreach ($gaoplusarray as $plugin => $plugininfo) {
            $path = $plugininfo['path'];
            $name = basename($path);
            $type = $plugininfo['type'];
            $group = $plugininfo['groupingname'];

            if (!isset($pluginarray[$group])) {
                $pluginarray[$group] = array();
            }

            $normalpath = $cfg->dirroot.'/'.$path;
            $hiddenpath = substr_replace($normalpath, '/.', strrpos($normalpath, '/'), 1);

            if (file_exists($normalpath)) {
                $info = 'enabled';
            } else if (file_exists($hiddenpath)) {
                $info = 'disabled';
            } else {
                $info = 'missing';
            }

            $pluginarray[$group][$path]['name'] = $name;
            $pluginarray[$group][$path]['type'] = $type;
            $pluginarray[$group][$path]['status'] = $info;
        }

        ksort($pluginarray);

        return $pluginarray;
    }

    /**
     * Fetch the list of enabled GAO+ plugins for this installation
     *
     * @return array $enabledplugins An array of enabled GAO+ grouping names
     */
    public function gaoplus_fetch_enabled() {
        $cfg = $this->get_config();

        $pdo = new db_pdo($cfg);

        if (($conn = $pdo->connect()) === false) {
            $this->error('Unable to connect to database with site credentials.');
        }

        $query = "SELECT value FROM {$cfg->prefix}config WHERE name = 'gaopluslist'";
        $result = $conn->query($query);

        if ($result !== false && $result->rowCount() > 0) {
            $resultassoc = $result->fetch(PDO::FETCH_ASSOC);
            $enabledplugins = explode(',', $resultassoc['value']);
        } else {
            $enabledplugins = array();
        }

        // Remove any blank entries
        foreach ($enabledplugins as $key => $data) {
            if (empty($enabledplugins[$key])) {
                unset($enabledplugins[$key]);
            }
        }

        return $enabledplugins;
    }

    /**
     * Display/Enable all supported GAO+ plugins as appropriate based on database status.
     */
    public function gaoplus_sync_from_db() {
        $cfg = $this->get_config();
        $enabled = $this->gaoplus_fetch_enabled();
        $plugins = new RLSCRIPTS_Moodle_Plugins();
        $moodleversion = get_moodleversion($cfg->rlscripts_git_branch);
        $gaoplusarray = $plugins->get_list_path($moodleversion, MOODLELIB_SOURCE_GAOPLUS);

        foreach ($gaoplusarray as $gaoplus) {
            $name = $gaoplus['path'];;
            if (!empty($gaoplus['groupingname'])) {
                $name = $gaoplus['groupingname'];
            }

            if (in_array($name, $enabled)) {
                $this->gaoplus_enable($moodleversion, $name, true);
            } else {
                $this->gaoplus_disable($moodleversion, $name, true);
            }
        }
    }

    /**
     * Save the list of enabled GAO+ plugins for this installation
     *
     * @param array $enabledplugins An array of enabled GAO+ grouping names
     */
    public function gaoplus_save_enabled($enabledplugins) {
        $cfg = $this->get_config();

        $gaopluslist = implode(',', $enabledplugins);

        $pdo = new db_pdo($cfg);

        if (($conn = $pdo->connect()) === false) {
            $this->error('Unable to connect to database with site credentials.');
        }

        $query = "SELECT value FROM {$cfg->prefix}config WHERE name='gaopluslist'";
        $result = $conn->query($query);

        if ($result !== false && $result->rowCount() > 0) {
            // Update value if it already exists in database
            $query = 'UPDATE '.$cfg->prefix.'config SET value="'.$gaopluslist.'" WHERE name="gaopluslist"';
            $result = $conn->exec($query);
            if ($result === false) {
                $this->error('Unable to update '.$cfg->prefix.'config table!');
            }
        } else {
            // Add new value to database
            $query = 'INSERT INTO '.$cfg->prefix.'config (name,value) VALUES ("gaopluslist","'.$gaopluslist.'")';
            $result = $conn->exec($query);
            if ($result === false) {
                $this->error('Unable to insert into '.$cfg->prefix.'config table!');
            }
        }
    }

    /**
     * Perform git diff code comparison
     *
     * @param string $path The directory path to Moodle installation we wish to check
     * @param string $overriderepository Optionally specify an alternate repository to check against
     * @param string $overridebranch Optionally specify an alternate branch to check against
     * @param string $output Optionally specify file to which output will be written
     * @param string $skipcheckout Optionally specify to never checkout a different commithash
     * @param string $tmpworkdir Optionally specify the directory path where temporary files and repository will be created
     */
    public function compare_code($path, $overriderepository = '', $overridebranch = '', $output = '', $skipcheckout = '', $tmpworkdir = '') {
        global $_CLI, $DIR_REFERENCE;

        $this->set_cli($_CLI);
        $cfg = $this->get_config();
        $tmpworkdir = empty($tmpworkdir) ? '/tmp' : rtrim($tmpworkdir, '/');

        // Create the temporary directory if it does not exist
        if (!file_exists($tmpworkdir)) {
            if (!mkdir($tmpworkdir)) {
                $this->error('Could not create temporary directory '.$tmpworkdir);
            }
        }

        // Make sure the temporary directory is writable
        if (!is_writable($tmpworkdir)) {
            $this->error('Temporary directory '.$tmpworkdir.' is not writable!');
        }

        // Create the temporary subdirectory if it does not exist
        if (!file_exists($tmpworkdir.'/tmpcomparecode')) {
            if (!mkdir($tmpworkdir.'/tmpcomparecode')) {
                $this->error('Could not create temporary directory '.$tmpworkdir.'/tmpcomparecode');
            }
        }

        // Make sure the temporary subdirectory is writable
        if (!is_writable($tmpworkdir.'/tmpcomparecode')) {
            $this->error('Temporary directory '.$tmpworkdir.'/tmpcomparecode is not writable!');
        }

        // Make sure specified output file is writable (if output file was specified)
        if (!empty($output)) {
            if (file_exists($output)) {
                // Prevent this script from overwriting anything
                $this->error('Specified output file '.$output.' already exists!');
            } else if (!touch($output)) {
                $this->error('Specified output file '.$output.' is not writable!');
            }
        }

        // Quick check if specified directory is a git install or not and fetch last commithash
        if (file_exists($path.'/.git')) {
            $isgitrepo = true;
            $cmd = 'cd '.$path.'; git log --format="%H" -n 1';
            $lastcommithash = exec($cmd);
        } else {
            $isgitrepo = false;
            $lastcommithash = '';
        }

        // Figure out matching repository to check against
        if (!empty($overriderepository)) {
            // Use the user specified repository
            $repository = $overriderepository;
        } else if ($isgitrepo) {
            // Fetch from config
            $repository = $cfg->rlscripts_git_repo;
        } else {
            // Since this is not a git install, let's just use the moodle mirror repository
            $repository = 'moodle.git';
        }

        // Figure out the versions and build date
        if (preg_match('/(\d+)\.(\d+)\.(\d+).*Build\:\s(\d+).*/', $cfg->release, $matches)) {
            $majorversion = $matches[1].$matches[2];
            $minorversion = $matches[3];
            $builddate = $matches[4];
        } else {
            $this->error('Unable to determine release from version.php!');
        }

        if (!empty($overridebranch)) {
            $branch = $overridebranch;
        } else {
            $branch = 'MOODLE_'.$majorversion.'_STABLE';
        }

        $this->message('Target directory to be checked: '.$path);
        $this->message('Repository to check against: '.$repository);
        $this->message('Branch to check against: '.$branch);

        // Erase any existing temporary repository
        if (file_exists($tmpworkdir.'/tmpcomparecode/moodle')) {
            $cmd = 'rm -rf '.$tmpworkdir.'/tmpcomparecode/moodle';
            $exitcode = $this->shell->exec($cmd);
            if ($exitcode != 0) {
                $this->error('Could not delete previous temporary repository!');
            }
        }

        // Set option for cloning from git reference, if applicable
        $refarg = '';
        $ref_repo = $DIR_REFERENCE.'/'.$repository;
        if (is_dir($ref_repo)) {
            $refarg = '--reference '.$ref_repo.' ';
        }

        // Clone the temporary repository
        $this->message('Cloning temporary repository (this may take a while)...');
        $cmd = 'git clone '.$refarg.'-b '.$branch.' ssh://rlgit-auto/'.$repository.' '.$tmpworkdir.'/tmpcomparecode/moodle';
        $exitcode = $this->shell->exec($cmd);
        if ($exitcode != 0) {
            $this->error('Could not clone temporary repository!');
        }

        // Find the last commit hash in temporary repository
        $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git log --format="%H" -n 1';
        $tmplastcommithash = exec($cmd);

        // Find the build date in temporary repository
        $cmd = 'cat '.$tmpworkdir.'/tmpcomparecode/moodle/version.php | grep "\$release"';
        $cmdresult = exec($cmd);
        if (preg_match('/\$release.*(\d+)\.(\d+)\.(\d+).*Build\:\s(\d+).*/', $cmdresult, $matches)) {
            $tmpmajorversion = $matches[1].$matches[2];
            $tmpminorversion = $matches[3];
            $tmpbuilddate = $matches[4];
        } else {
            $this->error('Unable to determine version from version.php in temporary repository!');
        }

        // Check if we are going to require checking out a different commit
        if ($skipcheckout) {
            $needcheckouthash = false;
        } else if ($isgitrepo) {
            if ($lastcommithash == $tmplastcommithash) {
                $needcheckouthash = false;
            } else {
                $needcheckouthash = true;
            }
        } else {
            if ($tmpmajorversion == $majorversion && $tmpminorversion == $minorversion && $tmpbuilddate == $builddate) {
                $needcheckouthash = false;
            } else {
                $needcheckouthash = true;
            }
        }

        if ($needcheckouthash) {
            $commithash = '';

            if (!empty($lastcommithash)) {
                // See if the source commit hash actually exists in the temporary repository
                $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git log | grep '.$lastcommithash.' | wc -l';
                $result = trim(exec($cmd));
                if ($result == 1) {
                    $commithash = $lastcommithash;
                }
            }

            if (empty($commithash)) {
                // If we didn't find a matching commit hash, attempt to find an appropriate hash based on build date
                $formattedbuilddate = date("Y-m-d", strtotime($builddate));
                $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git rev-list '.$branch.' -n 1 --first-parent --before='.$formattedbuilddate.' -- version.php';
                $result = trim(exec($cmd));
                if (!empty($result)) {
                    $commithash = $result;
                }
            }

            // If we have a commit hash to use, let's check it out now
            if (!empty($commithash)) {
                $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git checkout '.$commithash;
                $exitcode = $this->shell->exec($cmd);
                if ($exitcode != 0) {
                    $this->error('Could not checkout hash '.$commithash.' in temporary repository!');
                }
            }
        }

        // Delete all files in temporary repository
        $this->message('Deleting content of temporary repository...');
        $cmd = 'rm -rf '.$tmpworkdir.'/tmpcomparecode/moodle/*';
        $exitcode = $this->shell->exec($cmd);
        if ($exitcode != 0) {
            $this->error('Could not delete files in temporary repository!');
        }

        // Copy in content from repository path that we are checking
        $this->message('Copying content into temporary repository...');
        $cmd = 'cp -R '.$path.'/* '.$tmpworkdir.'/tmpcomparecode/moodle/';
        $exitcode = $this->shell->exec($cmd);
        if ($exitcode != 0) {
            $this->error('Could not copy files into temporary repository!');
        }

        // Do file permission cleanup to avoid false positive diff results
        $this->message('Synchronizing file permissions...');
        $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git diff -p | grep -E "^(diff|old mode|new mode)" | sed -e "s/^old/NEW/;s/^new/old/;s/^NEW/new/" | git apply';
        $exitcode = $this->shell->exec($cmd); // Ignore exitcode here because it will return a false negative if nothing is found to fix

        // Perform git diff
        $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git status > '.$tmpworkdir.'/tmpcomparecode/git-status.txt';
        $exitcode = $this->shell->exec($cmd);
        if ($exitcode != 0) {
            $this->error('Could not perform git status!');
        }

        $cmd = 'cd '.$tmpworkdir.'/tmpcomparecode/moodle; git diff -w > '.$tmpworkdir.'/tmpcomparecode/git-diff.txt';
        $exitcode = $this->shell->exec($cmd);
        if ($exitcode != 0) {
            $this->error('Could not perform git diff!');
        }

        // Display the output or copy it to a specified file
        if (empty($output)) {
            $this->message("GIT STATUS\n");
            readfile($tmpworkdir.'/tmpcomparecode/git-status.txt');
            $this->message("\n\nGIT DIFF\n");
            readfile($tmpworkdir.'/tmpcomparecode/git-diff.txt');
        } else {
            $fileout = @fopen($output, 'w');

            fputs($fileout, "GIT STATUS\n\n");
            $filesrc1 = @fopen($tmpworkdir.'/tmpcomparecode/git-status.txt', 'r');
            stream_copy_to_stream($filesrc1, $fileout);
            @fclose($filesrc1);

            fputs($fileout, "\n\nGIT DIFF\n\n");
            $filesrc2 = @fopen($tmpworkdir.'/tmpcomparecode/git-diff.txt', 'r');
            stream_copy_to_stream($filesrc2, $fileout);
            @fclose($filesrc2);

            @fclose($fileout);
            $this->message('Output written to '.$output);
        }

        // Clean up temporary repository
        $cmd = 'rm -rf '.$tmpworkdir.'/tmpcomparecode';
        $exitcode = $this->shell->exec($cmd);
        if ($exitcode != 0) {
            $this->error('Could not delete temporary directory!');
        }
    }

    /**
     * Set the commit id/hash of the path
     *
     * @param string $path The directory path to Moodle installation we wish to check
     * @param string $site The site to which the commit id is for
     * @return boolean True if id is set, false if invalid git path
     */
    public function set_commit_id($path, $site = 'sand') {

        if (!is_dir($path.'/.git')) {
            $this->error("Path: $path is not a git repository.");
            return false;
        }

        $git = new RLSCRIPTS_Git($path);
        // Turn off file permission tracking in case it's on.  It could block the upgrade for spurious reasons.
        $git->config('core.filemode', 'false');

        $commands = array(
            'fetch'     => '',
            'rev-parse' => "--short HEAD",
        );

        foreach ($commands as $command => $options) {
            $result = $git->exec($command, $options);
            if ($result === false) {
                $this->error("The git command ({$command} {$options}) failed.");
                break;
            }
        }

        $this->commitid[$site] = $result;
        return true;
    }

    /**
     * Get the commit id/hash of the site
     *
     * @param string $site The site to which the commit id is for
     * @return string The commit id of the site
     */
    public function get_commit_id($site = 'sand') {
        return $this->commitid[$site];
    }

    /**
     * Verify that the older commit id is an ancestor of the newer commitid
     *
     * @param string $oldpath This should be the older Moodle installation
     * @param string $newpath This should be the newer Moodle installation
     * @return string The commit id of the plugin
     */
    public function check_if_ancestor($oldpath, $newpath) {

        $gitold = new RLSCRIPTS_Git($oldpath);
        $oldtime = $gitold->exec("show -s --format=%ct");

        $gitnew = new RLSCRIPTS_Git($newpath);
        $newtime = $gitnew->exec("show -s --format=%ct");

        if ($oldtime <= $newtime) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Remove plugins that are missing dependencies.
     *
     * @param string $addons List of addons to be checked, checks the disk when blank.
     * @param int $branch The branch number for the plugins
     * @param string $sandboxdirroot  Set if upgrading to production, empty if upgrading sandbox
     * @return bool True for success
     */
    public function addon_remove_missing_dependencies($addons = array(), $branch = 0, $sandboxdirroot = '') {
        $this->prepare_git();

        if (count($addons) == 0) {
            $addons = $this->get_plugins(static::SOURCE_DB);
        }
        if ($branch == 0) {
            $branch = $this->determine_moodle_branch_number($this->git->branch());
        }

        $all = helper_moodle::LEVEL_GAO + helper_moodle::LEVEL_PLUS + helper_moodle::LEVEL_THIRD;
        $this->message("\nCollecting plugin information:");
        $dashboard = $this->get_dashboard_addons($all, $branch);
        $this->message("\nCleaning up plugin dependencies:");
        foreach ($addons as $type => $typelist) {
            foreach ($typelist as $name => $data) {
                $data = new RLSCRIPTS_Moodle_Addon($this, $type, $name);
                $path = $data->path;
                $available = array_key_exists($data->fullname, $dashboard);
                if ($available) {
                    $good = $this->addon_check_dependencies($dashboard, "{$data->fullname}", array(), 'disk');
                    if (!$good) {
                        // Remove this plugin
                        $this->status("Removing {$data->fullname} now");
                        $data->remove();
                    }
                }
            }
        }
        return true;
    }
}

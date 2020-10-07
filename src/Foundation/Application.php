<?php

namespace Gecche\Multidomain\Foundation;

class Application extends \Illuminate\Foundation\Application
{

    /**
     * The calculated domain storage path.
     *
     * @var string
     */
    protected $domainStoragePath;

    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected $environmentFile = null;

    /**
     * @var bool
     *
     * False is the domain has never been detected
     */
    protected $domainDetected = false;

    /**
     * Create a new application instance.
     * @param  string|null  $basePath
     * @param  string|null  $environmentPath
     */
    public function __construct($basePath = null, $environmentPath = null)
    {
        $environmentPath = $environmentPath ?? $basePath;
        $this->useEnvironmentPath(rtrim($environmentPath,'\/'));

        parent::__construct($basePath);
    }

    /**
     * Detect the application's current domain.
     *
     * @param array|string $envs
     * @return void;
     */
    public function detectDomain()
    {

        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : null;

        $domainDetector = new DomainDetector();
        $fullDomain = $domainDetector->detect($args);
        list($domain_scheme, $domain_name, $domain_port) = $domainDetector->split($fullDomain);
        $this['full_domain'] = $fullDomain;
        $this['domain'] = $domain_name;
        $this['domain_scheme'] = $domain_scheme;
        $this['domain_port'] = $domain_port;

        $this->domainDetected = true;
        return;
    }


    /**
     * Force the detection of the domain if it has never been detected.
     * It should not happens in standard flow.
     *
     * @return void;
     */
    protected function checkDomainDetection()
    {
        if (!$this->domainDetected)
            $this->detectDomain();
        return;
    }

    /**
     * Get or check the current application domain.
     *
     * @return string
     */
    public function domain()
    {

        $this->checkDomainDetection();

        if (count(func_get_args()) > 0) {
            return in_array($this['domain'], func_get_args());
        }

        return $this['domain'];
    }

    /**
     * Get or check the full current application domain with HTTP scheme and port.
     *
     * @param  mixed
     * @return string
     */
    public function fullDomain()
    {
        $this->checkDomainDetection();

        if (count(func_get_args()) > 0) {
            return in_array($this['full_domain'], func_get_args());
        }

        return $this['full_domain'];
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile()
    {
        return $this->environmentFile ?: $this->environmentFileDomain();
    }

    /**
     * Get the environment file of the current domain if it exists.
     * The file has to be named .env.<DOMAIN>
     * It returns the base .env file if a specific file does not exist.
     *
     * @return string
     */
    public function environmentFileDomain($domain = null)
    {
        $this->checkDomainDetection();

        if (is_null($domain)) {
            $domain = $this['domain'];
        }

        $envFile = $this->searchForEnvFileDomain(explode('.',$domain));

        return $envFile;

    }

    protected function searchForEnvFileDomain($tokens = []) {
        if (count($tokens) == 0) {
            return '.env';
        }

        $file = '.env.' . implode('.',$tokens);
        return file_exists(env_path($file))
            ? $file
            : $this->searchForEnvFileDomain(array_splice($tokens,1));
    }

    /**
     * Get the path to the storage directory of the current domain.
     * The storage path is a folder in the main storage laravel folder
     * with the sanitized domain name (dots are replaced with underscores)
     * It is sanitized in order to avoid problems with dots in paths especially
     * in the case of using array_dot notation.
     *
     * @return string
     */
    public function domainStoragePath($domain = null)
    {

        $this->checkDomainDetection();

        if (is_null($domain)) {
            $domain = $this['domain'];
        }

        $domainStoragePath = $this->searchForDomainStoragePath(parent::storagePath(),explode('.',$domain));

        $this->domainStoragePath = $domainStoragePath;

            return $domainStoragePath;

    }


    /*
     * Returns the exact storage path based on the domain (useful for package commands, could not exists)
     *
     * @return string
     */
    public function exactDomainStoragePath($domain = null)
    {
        $this->checkDomainDetection();

        if (is_null($domain)) {
            $domain = $this['domain'];
    }

        return rtrim(parent::storagePath() . DIRECTORY_SEPARATOR . domain_sanitized($domain),DIRECTORY_SEPARATOR);
    }

    /*
     * Laravel storagePath updated with domains.
     *
     * @return string
     */
    public function storagePath($domain = null)
    {
        return $this->storagePath ?: ($this->domainStoragePath ?: $this->domainStoragePath($domain)); // TODO: Change the autogenerated stub
    }


    protected function searchForDomainStoragePath($storagePath, $tokens = []) {
        if (count($tokens) == 0) {
            return $storagePath;
        }

        $tokensAsDomainString = implode('.',$tokens);

        $domainStoragePath = rtrim($storagePath . DIRECTORY_SEPARATOR . domain_sanitized($tokensAsDomainString), "\/");
        return file_exists($domainStoragePath)
            ? $domainStoragePath
            : $this->searchForDomainStoragePath($storagePath,array_splice($tokens,1));
    }


    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath()
    {
        return $_ENV['APP_CONFIG_CACHE'] ?? $this->getStandardCachedPath('config');
    }

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath()
    {
        return $_ENV['APP_ROUTES_CACHE'] ?? $this->getStandardCachedPath('routes');
    }

    /**
     * Get the path to the default cache file for config or routes.
     *
     * @param string
     * @return string
     */
    protected function getStandardCachedPath($type)
    {
        $domainSuffix = $this->getDomainCachedFileSuffix();
        return $this->bootstrapPath().'/cache/'.$type.$domainSuffix;
    }

    /**
     * Get a standard suffix for cache files depending upon the loaded .env file
     *
     * @return string
     */
    protected function getDomainCachedFileSuffix()
    {
        $envFile = $this->environmentFile();
        if ($envFile && $envFile == '.env')
            return '.php';
        $envDomainPart = substr($envFile, 5);
        return '-' . domain_sanitized($envDomainPart) . '.php';
    }

    /*
     * Get the list of installed domains
     *
     * @return Array
     */
    public function domainsList()
    {

        $domainsInConfig = config('domain.domains', []);

        $domains = [];

        foreach ($domainsInConfig as $domain) {
            $domains[$domain] = [
                'storage_path' => $this->domainStoragePath($domain),
                'env' => $this->environmentFileDomain($domain),
            ];
        }

        return $domains;

    }
}

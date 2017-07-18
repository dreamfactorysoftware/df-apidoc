<?php

namespace DreamFactory\Core\ApiDoc\Services;

use DreamFactory\Core\Components\StaticCacheable;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use Config;
use Log;
use ServiceManager;


/**
 * Swagger
 * API Documentation manager
 *
 */
class Swagger extends BaseRestService
{
    use StaticCacheable;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string The current API version
     */
    const API_VERSION = '2.0';
    /**
     * @const string The Swagger version
     */
    const SWAGGER_VERSION = '2.0';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_PREFIX = 'swagger:';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var int|null Native data format of this service - DataFormats enum value.
     */
    protected $nativeFormat = DataFormats::JSON;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     *
     * @return string The cache prefix associated with this service
     */
    protected static function getCachePrefix()
    {
        return static::SWAGGER_CACHE_PREFIX;
    }

    /**
     * @return array|string|bool
     */
    protected function handleGET()
    {
        if ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST)) {
            return ResourcesWrapper::wrapResources(ServiceManager::getServiceNames());
        }

        if (!empty($this->resource)) {
            return $this->getSwaggerForService($this->resource);
        }

        if ($this->request->getParameterAsBool(ApiOptions::REFRESH)) {
            $roleId = Session::getRoleId();
            static::clearCache($roleId);
        }

        return $this->getSwagger();
    }

    public static function clearCache($role_id)
    {
        static::removeFromCache($role_id);
    }

    /**
     * Main retrieve point for a list of swagger-able services
     * This builds the full swagger cache if it does not exist
     * @return array The JSON contents of the swagger api listing.
     * @throws UnauthorizedException
     * @throws \Exception
     */
    public function getSwagger()
    {
        if (Session::isSysAdmin()) {
            $roleId = 'admin';
        } elseif (empty($roleId = strval(Session::getRoleId()))) {
            throw new UnauthorizedException("Valid role or administrator required.");
        }

        if (null === ($content = static::getFromCache($roleId))) {
            Log::info('Building Swagger cache');

            //  Gather the services
            $paths = [];
            $definitions = static::getDefaultModels();
            $parameters = ApiOptions::getSwaggerGlobalParameters();
            $tags = [];

            //	Spin through services and pull the events
            /** @var ServiceInterface[] $services */
            if (!empty($services = ServiceManager::getServices(true))) {
                foreach ($services as $apiName => $service) {
                    $tags[$apiName] = $service->getDescription();
                    $content = $service->getApiDoc();
                    if (!empty($content)) {
                        $servicePaths = (array)array_get($content, 'paths');
                        $serviceDefs = (array)array_get($content, 'definitions');
                        $serviceParams = (array)array_get($content, 'parameters');

                        //  Add to the pile
                        $paths = array_merge($paths, $servicePaths);
                        $definitions = array_merge($definitions, $serviceDefs);
                        $parameters = array_merge($parameters, $serviceParams);
                    }
                }
            }

            // cache main api listing file
            $description = <<<HTML
HTML;

            $content = [
                'swagger'             => static::SWAGGER_VERSION,
                'securityDefinitions' => ['apiKey' => ['type' => 'apiKey', 'name' => 'api_key', 'in' => 'header']],
                'info'                => [
                    'title'       => 'DreamFactory Live API Documentation',
                    'description' => $description,
                    'version'     => Config::get('df.api_version', static::API_VERSION),
                    //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
                    'contact'     => [
                        'name'  => 'DreamFactory Software, Inc.',
                        'email' => 'support@dreamfactory.com',
                        'url'   => "https://www.dreamfactory.com/"
                    ],
                    'license'     => [
                        'name' => 'Apache 2.0',
                        'url'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
                    ]
                ],
                //'host'           => 'df.local',
                //'schemes'        => ['https'],
                'basePath'            => '/api/v2',
                'consumes'            => ['application/json', 'application/xml'],
                'produces'            => ['application/json', 'application/xml'],
                'paths'               => $paths,
                'definitions'         => $definitions,
                'tags'                => $tags,
                'parameters'          => $parameters,
            ];

            static::addToCache($roleId, $content, true);

            Log::info('Swagger cache build process complete');
        }

        return $content;
    }

    /**
     * This builds the full swagger cache for a particular service if it does not exist
     * @param string $name
     * @return array The JSON contents of the swagger api listing.
     * @throws UnauthorizedException
     */
    public function getSwaggerForService($name)
    {
        if (Session::isSysAdmin()) {
            $roleId = 'admin';
        } elseif (empty($roleId = strval(Session::getRoleId()))) {
            throw new UnauthorizedException("Valid role or administrator required.");
        }

//        if (null === ($content = static::getFromCache($name.':'.$roleId))) {
            Log::info("Building Swagger cache for $name");

            //  Gather the services
            $paths = [];
            $definitions = static::getDefaultModels();
            $parameters = ApiOptions::getSwaggerGlobalParameters();
            $tags = [];

            //	Spin through service and pull the events
            /** @var ServiceInterface $service */
            if (!empty($service = ServiceManager::getService($name))) {
                $tags[$name] = $service->getDescription();
                $content = $service->getApiDoc();
                if (!empty($content)) {
                    $servicePaths = (array)array_get($content, 'paths');
                    $serviceDefs = (array)array_get($content, 'definitions');
                    $serviceParams = (array)array_get($content, 'parameters');

                    //  Add to the pile
                    $paths = array_merge($paths, $servicePaths);
                    $definitions = array_merge($definitions, $serviceDefs);
                    $parameters = array_merge($parameters, $serviceParams);
                }
            }

            // cache main api listing file
            $description = <<<HTML
HTML;

            $content = [
                'swagger'             => static::SWAGGER_VERSION,
                'securityDefinitions' => ['apiKey' => ['type' => 'apiKey', 'name' => 'api_key', 'in' => 'header']],
                'info'                => [
                    'title'       => 'DreamFactory Live API Documentation',
                    'description' => $description,
                    'version'     => Config::get('df.api_version', static::API_VERSION),
                    //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
                    'contact'     => [
                        'name'  => 'DreamFactory Software, Inc.',
                        'email' => 'support@dreamfactory.com',
                        'url'   => "https://www.dreamfactory.com/"
                    ],
                    'license'     => [
                        'name' => 'Apache 2.0',
                        'url'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
                    ]
                ],
                //'host'           => 'df.local',
                //'schemes'        => ['https'],
                'basePath'            => '/api/v2',
                'consumes'            => ['application/json', 'application/xml'],
                'produces'            => ['application/json', 'application/xml'],
                'paths'               => $paths,
                'definitions'         => $definitions,
                'tags'                => $tags,
                'parameters'          => $parameters,
            ];

//            static::addToCache($name.':'.$roleId, $content, true);

            Log::info('Swagger cache build process complete');
//        }

        return $content;
    }

    public static function getDefaultModels()
    {
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'ResourceList' => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of accessible resources available to this service.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'Success'      => [
                'type'       => 'object',
                'properties' => [
                    'success' => [
                        'type'        => 'boolean',
                        'description' => 'True when API call was successful, false or error otherwise.',
                    ],
                ],
            ],
            'Error'        => [
                'type'       => 'object',
                'properties' => [
                    'code'    => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Error code.',
                    ],
                    'message' => [
                        'type'        => 'string',
                        'description' => 'String description of the error.',
                    ],
                ],
            ],
        ];
    }

    public static function getApiDocInfo($service)
    {
        $name = strtolower($service->name);
        $capitalized = camelize($service->name);

        return [
            'paths'       => [
                '/' . $name => [
                    'get' =>
                        [
                            'tags'        => [$name],
                            'summary'     => 'get' . $capitalized . '() - Retrieve the Swagger document.',
                            'operationId' => 'get' . $capitalized,
                            'parameters'  => [
                                [
                                    'name'        => 'file',
                                    'description' => 'Download the results of the request as a file.',
                                    'type'        => 'string',
                                    'in'          => 'query',
                                    'required'    => false,
                                ],
                            ],
                            'responses'   => [
                                '200'     => [
                                    'description' => 'Swagger Response',
                                    'schema'      => ['$ref' => '#/definitions/SwaggerResponse']
                                ],
                                'default' => [
                                    'description' => 'Error',
                                    'schema'      => ['$ref' => '#/definitions/Error']
                                ]
                            ],
                            'description' => 'This returns the Swagger file containing all API services.',
                        ],
                ],
            ],
            'definitions' => [
                'SwaggerResponse'   => [
                    'type'       => 'object',
                    'properties' => [
                        'apiVersion'  => [
                            'type'        => 'string',
                            'description' => 'Version of the API.',
                        ],
                        'swagger'     => [
                            'type'        => 'string',
                            'description' => 'Version of the Swagger API.',
                        ],
                        'basePath'    => [
                            'type'        => 'string',
                            'description' => 'Base path of the API.',
                        ],
                        'paths'       => [
                            'type'        => 'array',
                            'description' => 'Array of API paths.',
                            'items'       => [
                                '$ref' => '#/definitions/SwaggerPath',
                            ],
                        ],
                        'definitions' => [
                            'type'        => 'array',
                            'description' => 'Array of API definitions.',
                            'items'       => [
                                '$ref' => '#/definitions/SwaggerDefinition',
                            ],
                        ],
                    ],
                ],
                'SwaggerPath'       => [
                    'type'       => 'object',
                    'properties' => [
                        '__name__' => [
                            'type'        => 'string',
                            'description' => 'Path.',
                        ],
                    ],
                ],
                'SwaggerDefinition' => [
                    'type'       => 'object',
                    'properties' => [
                        '__name__' => [
                            'type'        => 'string',
                            'description' => 'Definition.',
                        ],
                    ],
                ],
            ]
        ];
    }
}

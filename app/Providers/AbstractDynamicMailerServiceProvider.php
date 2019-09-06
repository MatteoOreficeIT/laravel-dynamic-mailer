<?php
/**
 * Inspired from 
 * 1. https://github.com/laravel/framework/blob/5.6/src/Illuminate/Mail/TransportManager.php
 * 2. https://github.com/laravel/framework/blob/5.6/src/Illuminate/Mail/MailServiceProvider.php
 *
 * A service provider to register a custom mailer with Dynamic Options ( determined at Dependency Injection Container resolve-time )
 *
 * 1. resolve-time options: host, port, user, password, secure: [tls,ssl] could be determined at DI container resolve-time
 * 2. global options in mail.dynamic config file
 *
 * Define in mail.php such as structure for global options
 *
 *  'dynamic' => [
 *       'auth_mode' => 'plain',
 *       'timeout' => 2,            
 *       'stream' => [
 *           'ssl'=> [
 *               'verify_peer' => false,
 *               'verify_peer_name' => false,
 *           ]
 *       ]
 *   ],
 */

namespace App\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\TransportManager;
use Swift_SmtpTransport as SmtpTransport;
use Swift_Mailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

/**
 * You need to implement this method to return custom options for subclass
 * allowed key: host, port, user, password, secure: [tls,ssl]
 *      
 * @method array getSubclassOptions($options=[])
 */

abstract class AbstractDynamicMailerServiceProvider extends ServiceProvider
{

    abstract protected function getPrefix();
  
    protected $defer = true;

    protected $singleton = false;    

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        
        $this->registerSwiftMailer();
        $this->registerIlluminateMailer();

    }

    public function getDefaultOptions() {        
        return config('mail.dynamic.'.$this->getPrefix());
    }

    public function getOptions($options=[]) {        
        return array_replace_recursive(
            $this->getDefaultOptions(),
            method_exists($this,'getSubclassOptions') ? $this->getSubclassOptions($options) : $options,            
        );                
    }

    /**
     * Register the Illuminate mailer instance.
     *
     * @return void
     */
    protected function registerIlluminateMailer()
    {
        $prefix = $this->getPrefix();
        $this->app->bind($prefix.'.dynamic.mailer', function ($app,$options=[]) use($prefix) {            
            // Once we have create the mailer instance, we will set a container instance
            // on the mailer. This allows us to resolve mailer classes via containers
            // for maximum testability on said classes instead of passing Closures.
            $mailer = new Mailer(
                $app['view'], $app->makeWith($prefix.'.dynamic.swift.mailer',$options), $app['events']
            );
            // Next we will set all of the global addresses on this mailer, which allows
            // for easy unification of all "from" addresses as well as easy debugging
            // of sent messages since they get be sent into a single email address.
            foreach (['from', 'reply_to', 'to'] as $type) {
                $this->setGlobalAddress($mailer, $this->getOptions($options), $type);
            }
            return $mailer;
        },$this->singleton);
    }

    /**
     * Set a global address on the mailer by type.
     *
     * @param  \Illuminate\Mail\Mailer  $mailer
     * @param  array  $config
     * @param  string  $type
     * @return void
     */
    protected function setGlobalAddress($mailer, array $config, $type)
    {
        $address = Arr::get($config, $type);
        if (is_array($address) && isset($address['address'])) {
            $mailer->{'always'.Str::studly($type)}($address['address'], $address['name']);
        }
    }

    /**
     * Register the Swift Mailer instance.
     *
     * @return void
     */
    public function registerSwiftMailer()
    {
        $prefix = $this->getPrefix();
        $this->registerSwiftTransport();
        // Once we have the transporter registered, we will register the actual Swift
        // mailer instance, passing in the transport instances, which allows us to
        // override this transporter instances during app start-up if necessary.
        $this->app->bind($prefix.'.dynamic.swift.mailer', function ($app,$options=[]) use($prefix) {
            return new Swift_Mailer(
                $app->makeWith($prefix.'.dynamic.swift.transport',$options)->driver('dynamicSmtpSettings')
            );
        },$this->singleton);
    }

    
  
    /**
     * Register the Swift Transport instance.
     *
     * @return void
     */
    protected function registerSwiftTransport()
    {
        $prefix = $this->getPrefix();
        $this->app->bind($prefix.'.dynamic.swift.transport', function ($app,$options=[]) {
            return tap(new TransportManager($app),function($tm)use($options){
                $tm->extend('dynamicSmtpSettings',function(Application $app)use($options){
                    $config = $this->getOptions($options);
                    // The Swift SMTP transport instance will allow us to use any SMTP backend
                    // for delivering mail such as Sendgrid, Amazon SES, or a custom server
                    // a developer has available. We will just pass this configured host.
                    $transport = new SmtpTransport(
                        $config['host'],
                        $config['port']
                    );
                    if (!empty($config['secure'])) {
                        $transport->setEncryption($config['secure']);
                    }
                    // Once we have the transport we will check for the presence of a username
                    // and password. If we have it we will set the credentials on the Swift
                    // transporter instance so that we'll properly authenticate delivery.
                    if (isset($config['user']) && isset($config['password'])) {
                        $transport->setUsername($config['user']);
                        $transport->setPassword($config['password']);
                    }

                    /**
                     * @see https://goo.gl/9R13we
                     */
                    if (isset($config['auth_mode'])) {
                        $transport->setAuthMode($config['auth_mode']);
                    }

                    if (isset($config['timeout'])) {
                        $transport->setTimeout($config['timeout']);
                    }

                    // Next we will set any stream context options specified for the transport
                    // and then return it. The option is not required any may not be inside
                    // the configuration array at all so we'll verify that before adding.


                    if (isset($config['stream'])) {
                        $transport->setStreamOptions($config['stream']);
                    }
                    return $transport;

                });
            });
        },$this->singleton);
    }

    /**
     * Implement DeferrableProvider in subclass
     */
    public function provides()
    {        
        $prefix = $this->getPrefix();
        return [
            $prefix.'.dynamic.mailer', $prefix.'.dynamic.swift.mailer', $prefix.'.dynamic.swift.transport'
        ];
    }
}
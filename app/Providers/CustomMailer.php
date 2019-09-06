<?php

namespace App\Providers;

use Illuminate\Support\Str;
use Illuminate\Contracts\Support\DeferrableProvider;

class CustomMailer extends AbstractDynamicMailerServiceProvider implements DeferrableProvider {
    
    /**
     * options provided per subclass
     * 
     * @param array $options options provided with makeWith
     */
    protected function getSubclassOptions($options=[]) {

        // Scenario 1
        if(isset($options['flag_to_trigger_dynamic_behaviour'])) {
            // calculate options based on makeWith parameters
            $options['tls'] = $dynamicResult = false;
            // unset indirect options to prevent passing through
            unset($options['flag_to_trigger_dynamic_behaviour']);
            return $options;
        }

        // Scenario 2
        return [
            // Recover username and password from DB for example
            // 'username' => db_get_user($options['mailer_id']),
            // 'password' => db_get_password($options['mailer_id']),
        ];
    }

    /**
     * + the key in config/mail/dynamic.php for base options
     * + the key prefix this container's instances are bound to plus '.dynamic.mailer'
     */
    protected function getPrefix() {        
        return 'custom';
    }

}
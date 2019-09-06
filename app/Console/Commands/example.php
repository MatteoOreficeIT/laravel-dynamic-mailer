<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class example extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run example';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $custom = app('custom.dynamic.mailer',[
            // these options will be passed to getSubclassOptions() if exists in subclass
            // and also will be merged and replaced after : 
            // 1) mail.dynamic.custom
            // 2) getSubclassOptions()
            'username'=>'provided_in_user_code',
            'flag_to_trigger_dynamic_behaviour' => true
        ]);
    }
}

<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use App\Console\Command;

class ElasticWriteAliasRollover extends Command
{
    const COMMAND = 'service:elastic-write-alias-rollover';

    protected $signature = self::COMMAND . ' {--host=} {--write-alias=} {--read-alias=} {--auth=}';

    protected $description = 'Rollover write index and assign newly created index to read index';

    public function handle()
    {
        if (!$this->input->getOption('host')) {
            $this->line('<error>ERROR</error> You need to provide <info>--host</info> option with address to your Elastic instance (e.g. <info>--host=http://localhost:9200</info>)');
            return 1;
        }
        if (!$this->input->getOption('write-alias')) {
            $this->line('<error>ERROR</error> You need to provide <info>--write-alias</info> option with name of the write alias you use (e.g. <info>--write-alias=pageviews_write</info>)');
            return 1;
        }
        if (!$this->input->getOption('read-alias')) {
            $this->line('<error>ERROR</error> You need to provide <info>--read-alias</info> option with name of the read alias you use (e.g. <info>--read-alias=pageviews</info>)');
            return 1;
        }

        $client = new Client([
            'base_uri' => $this->input->getOption('host'),
        ]);

        $this->line('');
        $this->info('**** ' . self::COMMAND . ' (date: ' . (new Carbon())->format(DATE_RFC3339) . ') ****');

        $this->line(sprintf(
            "Executing rollover, host: <info>%s</info>, write-alias: <info>%s</info>, read-alias: <info>%s</info>",
            $this->input->getOption('host'),
            $this->input->getOption('write-alias'),
            $this->input->getOption('read-alias')
        ));
        
        $options = [];
        if ($this->input->getOption('auth')) {
            $auth = $this->input->getOption('auth');
            if (!Str::contains($auth, ':')) {
                $this->line("<error>ERROR</error> You need to provide <info>--auth</info> option with a name and a password (to Elastic instance) separated by ':', e.g. admin:password");
                return 1;
            }

            [$user, $pass] = explode(':', $auth, 2);
            $options = [
                'auth' => [$user, $pass]
            ];
        }

        // execute rollover; https://www.elastic.co/guide/en/elasticsearch/reference/6.3/indices-rollover-index.html
        $response = $client->post(sprintf("/%s/_rollover", $this->input->getOption('write-alias')), array_merge([
            'json' => [
                'conditions' => [
                    'max_age' => '31d',
                    'max_size' => '4gb',
                    //'max_docs' => 1, // condition for testing
                ],
            ],
        ], $options));

        $body = json_decode($response->getBody(), true);
        if (!$body['rolled_over']) {
            $this->line('  * Rollover condition not matched, done.');
            return 3;
        }

        $this->line(sprintf(
            '  * Rolled over, adding newly created <info>%s</info> to alias <info>%s</info>.',
            $body['new_index'],
            $this->input->getOption('read-alias')
        ));

        // if rollover happened, add newly created index to the read alias (so it contains all the indices)
        $client->post("/_aliases", array_merge([
            'json' => [
                'actions' => [
                    'add' => [
                        'index' => $body['new_index'],
                        'alias' => $this->input->getOption('read-alias')
                    ],
                ],
            ],
        ], $options));

        $this->line('  * Alias created, done.');
        return 0;
    }
}

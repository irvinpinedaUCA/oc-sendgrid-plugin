<?php namespace PanaKour\Sendgrid;

use Backend\Widgets\Form;
use GuzzleHttp\Client;
use System\Classes\PluginBase;
use System\Models\MailSetting;

class Plugin extends PluginBase
{
    const MODE_SENDGRID = 'sendgrid';

    public function pluginDetails()
    {
        return [
            'name'        => 'SendGrid Mailer Driver',
            'description' => 'This plugin let you sending email through Sendgrid driver.',
            'author'      => 'Panagiotis Koursaris',
        ];
    }

    public function boot()
    {
        \Event::listen('backend.form.extendFields', function (Form $widget) {

            if (!$widget->getController() instanceof \System\Controllers\Settings) {
                return;
            }

            if (!$widget->model instanceof MailSetting) {
                return;
            }

            $field = $widget->getField('send_mode');
            $field->options(array_merge($field->options(), [self::MODE_SENDGRID => "Sendgrid"]));

            $widget->addTabFields([
                'sendgrid_api_key' => [
                    "tab"     => "system::lang.mail.general",
                    'label'   => 'Sendgrid API Key',
                    'comment' => 'Enter your Sendgrid API key',
                    'trigger' => [
                        'action'    => 'show',
                        'field'     => 'send_mode',
                        'condition' => 'value[sendgrid]'
                    ]
                ],
            ]);
        });

        \App::extend('swift.transport', function (\Illuminate\Mail\TransportManager $manager) {
            return $manager->extend(self::MODE_SENDGRID, function () {
                $settings = MailSetting::instance();
                $client = new Client();
                return new SendgridTransport($client, $settings->sendgrid_api_key);
            });
        });

        MailSetting::extend(function ($model) {
            $model->bindEvent('model.beforeValidate', function () use ($model) {
                $model->rules['sendgrid_api_key'] = 'required_if:send_mode,' . self::MODE_SENDGRID;
            });
        });
    }

    public function register()
    {
        \Event::listen('mailer.register', function () {
            $settings = MailSetting::instance();
            if ($settings->send_mode === self::MODE_SENDGRID) {
                $config = \App::make('config');
                $config->set('services.sendgrid.api_key', $settings->sendgrid_api_key);
            }
        });

    }
}

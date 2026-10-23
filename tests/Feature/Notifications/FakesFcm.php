<?php

namespace Tests\Feature\Notifications;

use Illuminate\Support\Facades\Http;

/** Service account with a fresh RSA key, and fake Google endpoints. */
trait FakesFcm
{
    protected string $fcmPublicKey;

    protected ?string $fcmCredentialsPath = null;

    protected function configureFcm(array $sendResponses = []): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $this->fcmPublicKey = openssl_pkey_get_details($key)['key'];

        $this->fcmCredentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->fcmCredentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'loglasi-test',
            'client_email' => 'push@loglasi-test.iam.gserviceaccount.com',
            'private_key' => $private,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['notifications.fcm.credentials' => $this->fcmCredentialsPath, 'notifications.fcm.project_id' => null]);

        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            'fcm.googleapis.com/*' => $sendResponses === []
                ? Http::response(['name' => 'projects/loglasi-test/messages/1'])
                : Http::sequence($sendResponses),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->fcmCredentialsPath !== null && is_file($this->fcmCredentialsPath)) {
            unlink($this->fcmCredentialsPath);
        }

        parent::tearDown();
    }
}

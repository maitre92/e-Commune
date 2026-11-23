<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

/** Politique de confidentialité publique, lien obligatoire de la fiche Play Store de l'application mobile. */
class PrivacyPolicyTest extends TestCase
{
    public function test_the_privacy_policy_is_public(): void
    {
        config(['app.contact_email' => 'contact@example.test']);

        $this->get('/confidentialite')->assertOk()
            ->assertSee('Politique de confidentialité')
            ->assertSee('contact@example.test');
    }
}

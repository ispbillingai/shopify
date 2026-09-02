<?php
/**
 * Send customers to their account after signing in, not to the homepage.
 *
 * PrestaShop's order of preference at the end of AuthController::initContent()
 * is: a `back` URL, then $authRedirection, then __PS_BASE_URI__ — the homepage.
 *
 * The theme's login form posts `back=""`, so the first choice is skipped and
 * everyone lands on the homepage.
 *
 * This fills in `back` with a fully qualified account URL when the form left it
 * empty, which is the branch the vendor checks first and the one that only
 * accepts URLs on this shop (Tools::urlBelongsToShop). Setting $authRedirection
 * instead looked tidier but does not work: it is the *second* choice, and only
 * consulted when `back` is absent entirely.
 *
 * A real `back` — signing in part-way through checkout, say — is left alone, so
 * "carry on where you were" still behaves.
 */
class AuthController extends AuthControllerCore
{
    public function initContent(): void
    {
        if (Tools::isSubmit('submitLogin') && !Tools::getValue('back')) {
            $account = $this->context->link->getPageLink('my-account', true);

            $_POST['back'] = $account;
            $_REQUEST['back'] = $account;

            PrestaShopLogger::addLog('ShopFloor probe: auth override set back=' . $account, 1);
        }

        parent::initContent();
    }
}

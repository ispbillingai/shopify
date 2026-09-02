<?php
/**
 * Send customers to their account after signing in, not to the homepage.
 *
 * PrestaShop's own order of preference at the end of AuthController::initContent()
 * is: a `back` URL, then $authRedirection, then __PS_BASE_URI__ — the homepage.
 *
 * The first of those only counts when `back` is a *fully qualified* URL on this
 * shop (Tools::urlBelongsToShop). The storefront's ACCEDI link sends
 * `back=my-account`, which is a route name rather than a URL, so it is rejected
 * and everyone lands on the homepage instead of their account.
 *
 * Rather than restate the vendor's redirect logic here — it would go stale the
 * moment PrestaShop changes it — this fills in $authRedirection before the
 * parent runs, so its existing second choice becomes the account page. A real
 * `back` URL still wins, so "sign in and carry on where you were" is unaffected.
 *
 * Set in initContent() and not earlier on purpose: checkAccess() also reads
 * $authRedirection, and it urlencode()s whatever it finds. Handing it an
 * absolute URL there would produce a mangled redirect for anyone who opens the
 * login page while already signed in.
 */
class AuthController extends AuthControllerCore
{
    public function initContent(): void
    {
        if (empty($this->authRedirection)) {
            $this->authRedirection = $this->context->link->getPageLink('my-account');
        }

        parent::initContent();
    }
}

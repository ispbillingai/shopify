-- Switches off the vendor-branded back office menu entries.
--
-- Reversible: set active = 1 to bring any of them back. Nothing is deleted,
-- and no module is uninstalled, so the underlying features stay intact.
--
-- Applied against the `shopify` database.

-- "Wall of Fame" (ps_distributionapiclient)
UPDATE ps_tab SET active = 0
 WHERE class_name IN ('AdminPsdistributionapiclient', 'AdminPsdistributionapiclientCommunity');

-- "Marketplace" / module catalog upsell (ps_mbo)
UPDATE ps_tab SET active = 0
 WHERE class_name IN (
   'AdminPsMboModuleParent', 'AdminPsMboModule', 'AdminPsMboRecommended',
   'AdminPsMboTheme', 'AdminPsMboSelection'
 );

-- "Care Center" and the academy link (ps_classic_edition).
-- The HOME tab from the same module is deliberately left alone: it is the
-- admin landing page, not branding.
UPDATE ps_tab SET active = 0
 WHERE class_name IN (
   'AdminPsClassicEditionHomepageController',
   'AdminPsClassicEditionPsAcademyController'
 );

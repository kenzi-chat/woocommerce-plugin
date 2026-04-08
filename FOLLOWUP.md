# FOLLOWUP

Known-but-deferred work for the Kenzi WooCommerce plugin. Items here came out
of code review of the sibling OroCommerce bundle — both plugins follow the
same credential-delivery pattern, so the same latent issues apply here. Each
has a documented reason for not being done yet.

---

## 1. Bind `credentials_delivered` to connection identity

**Source:** Code review of `platforms/orocommerce/src/Credential/CredentialDelivery.php`; applies here because `platforms/woocommerce/src/CredentialDelivery.php` uses the same pattern.

**Symptom:** After delivering credentials successfully, disconnecting, and
reconnecting to a *different* Kenzi workspace, `CredentialDelivery::maybeDeliver()`
returns early from the `Settings::isCredentialsDelivered()` guard and skips the
PATCH. The new workspace ends up connected for webhooks but without WooCommerce
REST API credentials, so backfill fails with 401.

**Root cause:** `isCredentialsDelivered()` reads a plain boolean WordPress
option. It has no binding to *which* connection the delivery was performed
for, so state from a previous connection leaks across a reconnect.

**Proposed fix:** Bind delivery state to a short hash of `shared_secret`.

- `OPTION_CREDENTIALS_DELIVERED` changes from storing `'1'`/`'0'` to storing
  a 16-char hash (empty = not delivered). Rename the option constant
  (e.g. `OPTION_CREDENTIALS_DELIVERED_SECRET_HASH`) or keep the name and
  just change the stored value shape — either way, bump a plugin data version
  and run a one-time upgrade routine.
- `Settings::isCredentialsDelivered()` becomes:
  ```php
  $stored = (string) get_option(self::OPTION_CREDENTIALS_DELIVERED_SECRET_HASH, '');
  if ($stored === '') {
      return false;
  }
  $current = substr(hash('sha256', (string) ChatSettings::getSharedSecret()), 0, 16);
  return $stored === $current;
  ```
- On successful delivery, store `substr(hash('sha256', $secret), 0, 16)` instead
  of `true`.
- Upgrade routine in `Plugin::onUpgrade()` (or similar): if the old boolean
  option is `'1'`, rewrite it with the hash of the currently-stored
  `shared_secret`. If `'0'` or absent, clear it.

**Cost:** ~4 touch points (`Settings.php`, `CredentialDelivery.php`, the upgrade
routine, one test file). No schema migration system to negotiate — it's just
WordPress options.

**Why deferred:** Reconnecting a production WordPress site to a different Kenzi
workspace is vanishingly rare. Not worth the upgrade-routine risk until we see
it in the wild.

**Revisit trigger:** Any support ticket where a customer reports "backfill
is broken after reconnecting" or similar.

---

## 2. Decouple API-key owner from the current admin request

**Source:** Code review of the OroCommerce bundle (same pattern here).

**Symptom:** `CredentialDelivery::generateApiKey()` uses `get_current_user_id()`
to set the `user_id` on the WooCommerce API key row. This only works inside an
authenticated admin request — any WP-CLI command, cron job, or background
worker that calls `maybeDeliver()` will see `get_current_user_id() === 0` and
create an orphaned API key.

**Root cause:** The owning user is read at *delivery time* from the request
context instead of being captured at *connect time* and persisted.

**Proposed fix:**
1. Add a new option `OPTION_API_KEY_OWNER_USER_ID`.
2. When the connect handler in `SettingsPage` (or wherever the connection is
   stored) persists the shared secret, also persist the current user id.
3. `generateApiKey()` reads the stored user id instead of calling
   `get_current_user_id()`.
4. On `cleanup()`, clear the stored user id.

**Why deferred:** Less urgent than the Oro variant because WordPress has no
equivalent to Oro's message queue — `maybeDeliver()` today only runs on
`admin_init`, which is always in an authenticated admin context. The
constraint is purely latent.

**Revisit trigger:** Any WP-CLI command, cron job, or Action Scheduler task
that ends up calling `CredentialDelivery::maybeDeliver()` outside a logged-in
admin request. Do the work as part of whichever feature introduces that path.

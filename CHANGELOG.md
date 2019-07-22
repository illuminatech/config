Laravel Persistent Configuration Repository
===========================================

1.0.3, July 22, 2019
--------------------

- Bug #6: Fixed `PersistentRepository::restore()` throws an exception after certain `Item` config change like adding encryption (klimov-paul)


1.0.2, June 18, 2019
--------------------

- Bug #3: Fixed retrieval fo multiple keys with default values via `PersistentRepository::get()` (klimov-paul)


1.0.1, June 11, 2019
--------------------

- Bug #2: Fixed ability to pass list of keys as an array to `PersistentRepository::get()` (klimov-paul)
- Enh: Added `Item::$options` for the custom item options specification (klimov-paul)


1.0.0, May 22, 2019
-------------------

- Initial release.

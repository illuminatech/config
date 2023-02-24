Laravel Persistent Configuration Repository
===========================================

1.4.0 Under Development
-----------------------

- Enh: Added support for "illuminate/support" 10.0 (klimov-paul)
- Enh: Added strict types declarations for the return types of the methods inherited from `ArrayAccess` at `PersistentRepository` (klimov-paul)


1.3.2, July 12, 2022
--------------------

- Bug #16: Fixed missing 'encrypt' key in `Item::toArray()` result (klimov-paul)


1.3.1, February 9, 2022
-----------------------

- Enh: Added support for "illuminate/support" 9.0 (klimov-paul)


1.3.0, September 9, 2020
------------------------

- Bug #13: Fixed validation for the item ID containing dot ('.') (klimov-paul)
- Enh: Added support for "illuminate/support" 8.0 (klimov-paul)


1.2.0, March 11, 2020
---------------------

- Enh #12: Fixed typo in interface name from `StorageContact` to `StorageContract` (klimov-paul)


1.1.1, March 5, 2020
--------------------

- Bug: Fixed `PersistentRepository::save()` looses values on partial save with cache enabled (klimov-paul)
- Enh: Created `AbstractPersistentConfigServiceProvider` providing a scaffold for the application-wide persistent configuration setup (klimov-paul)
- Enh #10: Added support for "illuminate/support" 7.0 (drbyte, klimov-paul)


1.1.0, September 6, 2019
------------------------

- Enh #8: Added support for "illuminate/support" 6.0 (klimov-paul)


1.0.4, August 28, 2019
----------------------

- Bug #7: Fixed `PersistentRepository::synchronize()` does not save values for the items with custom `id` (klimov-paul)


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

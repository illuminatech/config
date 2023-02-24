Upgrading Instructions for Laravel Composite Validation
=======================================================

!!!IMPORTANT!!!

The following upgrading instructions are cumulative. That is,
if you want to upgrade from version A to version C and there is
version B between A and C, you need to following the instructions
for both A and B.

Upgrade from 1.2.0
------------------

* Added strict types declarations for the return types of the methods inherited from `ArrayAccess` at `Illuminatech\Config\PersistentRepository`.
  Make sure you use compatible return type declarations in case you override these methods.


Upgrade from 1.2.0
------------------

* "illuminate/support" package requirements were raised to 7.26.0. Make sure to upgrade your code accordingly.


Upgrade from 1.1.1
------------------

* Interface `Illuminatech\Config\StorageContact` has been renamed to `Illuminatech\Config\StorageContract`.
  Check references to this interface in your code and fix them accordingly.


Upgrade from 1.0.4
------------------

* "illuminate/support" package requirements were raised to 6.0. Make sure to upgrade your code accordingly.

* "illuminate/database" package is no longer required by this extension, make sure you add it to your `composer.json`
  in case you use `StorageDb` or `StorageEloquent` storage.

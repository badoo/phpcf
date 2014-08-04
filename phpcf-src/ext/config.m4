dnl $Id$
dnl config.m4 for extension phpcf

dnl Otherwise use enable:

PHP_ARG_ENABLE(phpcf, whether to enable phpcf support,
[  --disable-phpcf     Disable phpcf support], yes)

if test "$PHP_PHPCF" != "no"; then
  PHP_NEW_EXTENSION(phpcf, phpcf.c, $ext_shared)
  PHP_ADD_MAKEFILE_FRAGMENT
fi

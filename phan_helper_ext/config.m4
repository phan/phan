PHP_ARG_ENABLE([phan_helper],
  [whether to enable phan_helper support],
  [AS_HELP_STRING([--enable-phan-helper],
    [Enable phan_helper support])],
  [no])

if test "$PHP_PHAN_HELPER" != "no"; then
  AC_DEFINE(HAVE_PHAN_HELPER, 1, [ Have phan_helper support ])
  PHP_NEW_EXTENSION(phan_helper, phan_helper.c, $ext_shared)
fi
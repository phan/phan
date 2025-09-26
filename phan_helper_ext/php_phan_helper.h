#ifndef PHP_PHAN_HELPER_H
#define PHP_PHAN_HELPER_H

extern zend_module_entry phan_helper_module_entry;
#define phpext_phan_helper_ptr &phan_helper_module_entry

#define PHP_PHAN_HELPER_VERSION "1.0.0"

#ifdef PHP_WIN32
#   define PHP_PHAN_HELPER_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#   define PHP_PHAN_HELPER_API __attribute__ ((visibility("default")))
#else
#   define PHP_PHAN_HELPER_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

// Fast type set structure for object identity-based lookups
typedef struct {
    void **objects;     // Array of object pointers
    size_t capacity;    // Current capacity
    size_t size;        // Number of elements
    size_t threshold;   // Resize threshold
} phan_type_set_t;

// Function declarations
PHP_MINIT_FUNCTION(phan_helper);
PHP_MSHUTDOWN_FUNCTION(phan_helper);
PHP_RINIT_FUNCTION(phan_helper);
PHP_RSHUTDOWN_FUNCTION(phan_helper);
PHP_MINFO_FUNCTION(phan_helper);

// PHP function declarations
PHP_FUNCTION(phan_typeset_create);
PHP_FUNCTION(phan_typeset_add);
PHP_FUNCTION(phan_typeset_contains);
PHP_FUNCTION(phan_typeset_size);
PHP_FUNCTION(phan_typeset_destroy);
PHP_FUNCTION(phan_in_array_fast);

// Union Type operations
PHP_FUNCTION(phan_union_can_cast_to);
PHP_FUNCTION(phan_union_merge_types);
PHP_FUNCTION(phan_union_intersect_types);
PHP_FUNCTION(phan_union_diff_types);

// Array operations
PHP_FUNCTION(phan_array_unique_objects);
PHP_FUNCTION(phan_array_merge_unique);
PHP_FUNCTION(phan_array_filter_types);

// String operations
PHP_FUNCTION(phan_normalize_path);
PHP_FUNCTION(phan_fqsen_parse);

// Internal functions
phan_type_set_t* phan_typeset_new(void);
int phan_typeset_add_object(phan_type_set_t *set, zend_object *obj);
int phan_typeset_contains_object(phan_type_set_t *set, zend_object *obj);
void phan_typeset_free(phan_type_set_t *set);

#ifdef ZTS
#define PHAN_HELPER_G(v) TSRMG(phan_helper_globals_id, zend_phan_helper_globals *, v)
#else
#define PHAN_HELPER_G(v) (phan_helper_globals.v)
#endif

#endif  /* PHP_PHAN_HELPER_H */
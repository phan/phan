#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_phan_helper.h"

/* True global resources - no need for thread safety here */
static int le_phan_typeset;

#define PHAN_TYPESET_INITIAL_CAPACITY 16
#define PHAN_TYPESET_LOAD_FACTOR 0.75

/* {{{ PHP_INI */
PHP_INI_BEGIN()
    /* Add any INI entries here if needed */
PHP_INI_END()
/* }}} */

/* {{{ phan_typeset resource destructor */
static void phan_typeset_dtor(zend_resource *rsrc)
{
    phan_type_set_t *set = (phan_type_set_t *)rsrc->ptr;
    if (set) {
        phan_typeset_free(set);
    }
}
/* }}} */

/* {{{ PHP_MINIT_FUNCTION */
PHP_MINIT_FUNCTION(phan_helper)
{
    REGISTER_INI_ENTRIES();

    // Register the resource type for type sets
    le_phan_typeset = zend_register_list_destructors_ex(
        phan_typeset_dtor, NULL, "phan_typeset", module_number);

    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION */
PHP_MSHUTDOWN_FUNCTION(phan_helper)
{
    UNREGISTER_INI_ENTRIES();
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_RINIT_FUNCTION */
PHP_RINIT_FUNCTION(phan_helper)
{
#if defined(COMPILE_DL_PHAN_HELPER) && defined(ZTS)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_RSHUTDOWN_FUNCTION */
PHP_RSHUTDOWN_FUNCTION(phan_helper)
{
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION */
PHP_MINFO_FUNCTION(phan_helper)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "phan_helper support", "enabled");
    php_info_print_table_row(2, "Version", PHP_PHAN_HELPER_VERSION);
    php_info_print_table_end();

    DISPLAY_INI_ENTRIES();
}
/* }}} */

/* {{{ Internal typeset implementation */

phan_type_set_t* phan_typeset_new(void)
{
    phan_type_set_t *set = emalloc(sizeof(phan_type_set_t));
    if (!set) {
        return NULL;
    }

    set->capacity = PHAN_TYPESET_INITIAL_CAPACITY;
    set->size = 0;
    set->threshold = (size_t)(PHAN_TYPESET_INITIAL_CAPACITY * PHAN_TYPESET_LOAD_FACTOR);

    set->objects = ecalloc(PHAN_TYPESET_INITIAL_CAPACITY, sizeof(void*));
    if (!set->objects) {
        efree(set);
        return NULL;
    }

    return set;
}

static size_t phan_hash_object(zend_object *obj, size_t capacity)
{
    // Use object handle as hash - it's unique within the request
    uintptr_t addr = (uintptr_t)obj;
    return addr % capacity;
}

static int phan_typeset_resize(phan_type_set_t *set)
{
    size_t old_capacity = set->capacity;
    void **old_objects = set->objects;

    // Double the capacity
    set->capacity = old_capacity * 2;
    set->threshold = (size_t)(set->capacity * PHAN_TYPESET_LOAD_FACTOR);
    set->objects = ecalloc(set->capacity, sizeof(void*));

    if (!set->objects) {
        // Restore old state on failure
        set->capacity = old_capacity;
        set->threshold = (size_t)(old_capacity * PHAN_TYPESET_LOAD_FACTOR);
        set->objects = old_objects;
        return FAILURE;
    }

    // Rehash all existing objects
    size_t old_size = set->size;
    set->size = 0;

    for (size_t i = 0; i < old_capacity; i++) {
        if (old_objects[i] != NULL) {
            zend_object *obj = (zend_object*)old_objects[i];
            size_t hash = phan_hash_object(obj, set->capacity);

            // Linear probing to find empty slot
            while (set->objects[hash] != NULL) {
                hash = (hash + 1) % set->capacity;
            }

            set->objects[hash] = obj;
            set->size++;
        }
    }

    efree(old_objects);
    return SUCCESS;
}

int phan_typeset_add_object(phan_type_set_t *set, zend_object *obj)
{
    if (!set || !obj) {
        return FAILURE;
    }

    // Check if resize is needed
    if (set->size >= set->threshold) {
        if (phan_typeset_resize(set) == FAILURE) {
            return FAILURE;
        }
    }

    size_t hash = phan_hash_object(obj, set->capacity);

    // Linear probing to find empty slot or existing object
    while (set->objects[hash] != NULL) {
        if (set->objects[hash] == obj) {
            return SUCCESS; // Already exists
        }
        hash = (hash + 1) % set->capacity;
    }

    // Add new object
    set->objects[hash] = obj;
    set->size++;
    return SUCCESS;
}

int phan_typeset_contains_object(phan_type_set_t *set, zend_object *obj)
{
    if (!set || !obj || set->size == 0) {
        return 0;
    }

    size_t hash = phan_hash_object(obj, set->capacity);

    // Linear probing to find object
    while (set->objects[hash] != NULL) {
        if (set->objects[hash] == obj) {
            return 1; // Found
        }
        hash = (hash + 1) % set->capacity;
    }

    return 0; // Not found
}

void phan_typeset_free(phan_type_set_t *set)
{
    if (set) {
        if (set->objects) {
            efree(set->objects);
        }
        efree(set);
    }
}

/* }}} */

/* {{{ PHP API Functions */

/* {{{ proto resource phan_typeset_create()
   Create a new typeset for fast object lookups */
PHP_FUNCTION(phan_typeset_create)
{
    if (zend_parse_parameters_none() == FAILURE) {
        RETURN_FALSE;
    }

    phan_type_set_t *set = phan_typeset_new();
    if (!set) {
        RETURN_FALSE;
    }

    RETURN_RES(zend_register_resource(set, le_phan_typeset));
}
/* }}} */

/* {{{ proto bool phan_typeset_add(resource typeset, object obj)
   Add an object to the typeset */
PHP_FUNCTION(phan_typeset_add)
{
    zval *res;
    zval *obj;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "ro", &res, &obj) == FAILURE) {
        RETURN_FALSE;
    }

    if (Z_TYPE_P(obj) != IS_OBJECT) {
        php_error_docref(NULL, E_WARNING, "Second parameter must be an object");
        RETURN_FALSE;
    }

    phan_type_set_t *set = (phan_type_set_t*)zend_fetch_resource(
        Z_RES_P(res), "phan_typeset", le_phan_typeset);
    if (!set) {
        RETURN_FALSE;
    }

    int result = phan_typeset_add_object(set, Z_OBJ_P(obj));
    RETURN_BOOL(result == SUCCESS);
}
/* }}} */

/* {{{ proto bool phan_typeset_contains(resource typeset, object obj)
   Check if the typeset contains an object */
PHP_FUNCTION(phan_typeset_contains)
{
    zval *res;
    zval *obj;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "ro", &res, &obj) == FAILURE) {
        RETURN_FALSE;
    }

    if (Z_TYPE_P(obj) != IS_OBJECT) {
        php_error_docref(NULL, E_WARNING, "Second parameter must be an object");
        RETURN_FALSE;
    }

    phan_type_set_t *set = (phan_type_set_t*)zend_fetch_resource(
        Z_RES_P(res), "phan_typeset", le_phan_typeset);
    if (!set) {
        RETURN_FALSE;
    }

    int result = phan_typeset_contains_object(set, Z_OBJ_P(obj));
    RETURN_BOOL(result);
}
/* }}} */

/* {{{ proto int phan_typeset_size(resource typeset)
   Get the size of the typeset */
PHP_FUNCTION(phan_typeset_size)
{
    zval *res;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "r", &res) == FAILURE) {
        RETURN_FALSE;
    }

    phan_type_set_t *set = (phan_type_set_t*)zend_fetch_resource(
        Z_RES_P(res), "phan_typeset", le_phan_typeset);
    if (!set) {
        RETURN_FALSE;
    }

    RETURN_LONG(set->size);
}
/* }}} */


/* {{{ Union Type Operations */

/* {{{ proto bool phan_union_can_cast_to(array source_types, array target_types)
   Fast type casting check for union types */
PHP_FUNCTION(phan_union_can_cast_to)
{
    zval *source_types, *target_types;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "aa", &source_types, &target_types) == FAILURE) {
        RETURN_FALSE;
    }

    // Create hash sets for fast lookups
    HashTable source_hash, target_hash;
    zend_hash_init(&source_hash, zend_hash_num_elements(Z_ARRVAL_P(source_types)), NULL, NULL, 0);
    zend_hash_init(&target_hash, zend_hash_num_elements(Z_ARRVAL_P(target_types)), NULL, NULL, 0);

    // Populate target hash for O(1) lookups
    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(target_types), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            zend_hash_index_add(&target_hash, obj_id, entry);
        }
    } ZEND_HASH_FOREACH_END();

    // Check if any source type exists in target
    bool can_cast = false;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(source_types), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            if (zend_hash_index_exists(&target_hash, obj_id)) {
                can_cast = true;
                break;
            }
        }
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&source_hash);
    zend_hash_destroy(&target_hash);

    RETURN_BOOL(can_cast);
}
/* }}} */

/* {{{ proto array phan_union_merge_types(array types1, array types2)
   Merge two type arrays, removing duplicates by object identity */
PHP_FUNCTION(phan_union_merge_types)
{
    zval *types1, *types2;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "aa", &types1, &types2) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);
    HashTable seen_objects;
    zend_hash_init(&seen_objects,
        zend_hash_num_elements(Z_ARRVAL_P(types1)) + zend_hash_num_elements(Z_ARRVAL_P(types2)),
        NULL, NULL, 0);

    // Add all objects from first array
    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types1), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            if (!zend_hash_index_exists(&seen_objects, obj_id)) {
                zend_hash_index_add(&seen_objects, obj_id, entry);
                zval copy;
                ZVAL_COPY(&copy, entry);
                add_next_index_zval(return_value, &copy);
            }
        }
    } ZEND_HASH_FOREACH_END();

    // Add unique objects from second array
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types2), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            if (!zend_hash_index_exists(&seen_objects, obj_id)) {
                zend_hash_index_add(&seen_objects, obj_id, entry);
                zval copy;
                ZVAL_COPY(&copy, entry);
                add_next_index_zval(return_value, &copy);
            }
        }
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&seen_objects);
}
/* }}} */

/* {{{ proto array phan_union_intersect_types(array types1, array types2)
   Find intersection of two type arrays by object identity */
PHP_FUNCTION(phan_union_intersect_types)
{
    zval *types1, *types2;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "aa", &types1, &types2) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);
    HashTable types2_hash;
    zend_hash_init(&types2_hash, zend_hash_num_elements(Z_ARRVAL_P(types2)), NULL, NULL, 0);

    // Build hash of second array for fast lookup
    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types2), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            zend_hash_index_add(&types2_hash, obj_id, entry);
        }
    } ZEND_HASH_FOREACH_END();

    // Find objects from first array that exist in second
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types1), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            if (zend_hash_index_exists(&types2_hash, obj_id)) {
                zval copy;
                ZVAL_COPY(&copy, entry);
                add_next_index_zval(return_value, &copy);
            }
        }
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&types2_hash);
}
/* }}} */

/* {{{ proto array phan_union_diff_types(array types1, array types2)
   Find difference of two type arrays by object identity */
PHP_FUNCTION(phan_union_diff_types)
{
    zval *types1, *types2;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "aa", &types1, &types2) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);
    HashTable types2_hash;
    zend_hash_init(&types2_hash, zend_hash_num_elements(Z_ARRVAL_P(types2)), NULL, NULL, 0);

    // Build hash of second array for fast lookup
    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types2), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            zend_hash_index_add(&types2_hash, obj_id, entry);
        }
    } ZEND_HASH_FOREACH_END();

    // Find objects from first array that don't exist in second
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types1), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            if (!zend_hash_index_exists(&types2_hash, obj_id)) {
                zval copy;
                ZVAL_COPY(&copy, entry);
                add_next_index_zval(return_value, &copy);
            }
        }
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&types2_hash);
}
/* }}} */

/* }}} */

/* {{{ Array Operations */

/* {{{ proto array phan_array_unique_objects(array objects)
   Remove duplicate objects from array using object identity */
PHP_FUNCTION(phan_array_unique_objects)
{
    zval *objects;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "a", &objects) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);
    HashTable seen_objects;
    zend_hash_init(&seen_objects, zend_hash_num_elements(Z_ARRVAL_P(objects)), NULL, NULL, 0);

    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(objects), entry) {
        if (Z_TYPE_P(entry) == IS_OBJECT) {
            zend_ulong obj_id = (zend_ulong)Z_OBJ_P(entry);
            if (!zend_hash_index_exists(&seen_objects, obj_id)) {
                zend_hash_index_add(&seen_objects, obj_id, entry);
                zval copy;
                ZVAL_COPY(&copy, entry);
                add_next_index_zval(return_value, &copy);
            }
        } else {
            // For non-objects, add directly (could enhance this with other types)
            zval copy;
            ZVAL_COPY(&copy, entry);
            add_next_index_zval(return_value, &copy);
        }
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&seen_objects);
}
/* }}} */

/* {{{ proto array phan_array_merge_unique(array arr1, array arr2)
   Merge two arrays and remove duplicates in one pass */
PHP_FUNCTION(phan_array_merge_unique)
{
    zval *arr1, *arr2;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "aa", &arr1, &arr2) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);
    HashTable seen_values;
    zend_hash_init(&seen_values,
        zend_hash_num_elements(Z_ARRVAL_P(arr1)) + zend_hash_num_elements(Z_ARRVAL_P(arr2)),
        NULL, NULL, 0);

    // Process first array
    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(arr1), entry) {
        zend_string *key = zval_get_string(entry);
        if (!zend_hash_exists(&seen_values, key)) {
            zend_hash_add(&seen_values, key, entry);
            zval copy;
            ZVAL_COPY(&copy, entry);
            add_next_index_zval(return_value, &copy);
        }
        zend_string_release(key);
    } ZEND_HASH_FOREACH_END();

    // Process second array
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(arr2), entry) {
        zend_string *key = zval_get_string(entry);
        if (!zend_hash_exists(&seen_values, key)) {
            zend_hash_add(&seen_values, key, entry);
            zval copy;
            ZVAL_COPY(&copy, entry);
            add_next_index_zval(return_value, &copy);
        }
        zend_string_release(key);
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&seen_values);
}
/* }}} */

/* {{{ proto array phan_array_filter_types(array types, int filter_mask)
   Filter array based on bitmask (placeholder for type-specific filtering) */
PHP_FUNCTION(phan_array_filter_types)
{
    zval *types;
    zend_long filter_mask;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "al", &types, &filter_mask) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);

    // For now, just copy all elements (can be enhanced with specific type filtering)
    zval *entry;
    ZEND_HASH_FOREACH_VAL(Z_ARRVAL_P(types), entry) {
        zval copy;
        ZVAL_COPY(&copy, entry);
        add_next_index_zval(return_value, &copy);
    } ZEND_HASH_FOREACH_END();
}
/* }}} */

/* }}} */

/* {{{ String Operations */


/* {{{ proto array phan_fqsen_parse(string fqsen)
   Parse FQSEN into components [namespace, class, method] */
PHP_FUNCTION(phan_fqsen_parse)
{
    zend_string *fqsen;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "S", &fqsen) == FAILURE) {
        RETURN_FALSE;
    }

    array_init(return_value);

    char *str = ZSTR_VAL(fqsen);
    size_t len = ZSTR_LEN(fqsen);

    // Find last backslash for namespace/class separation
    char *last_backslash = NULL;
    char *last_double_colon = NULL;

    for (size_t i = 0; i < len; i++) {
        if (str[i] == '\\') {
            last_backslash = str + i;
        } else if (i < len - 1 && str[i] == ':' && str[i + 1] == ':') {
            last_double_colon = str + i;
            i++; // Skip the second colon
        }
    }

    // Extract namespace
    if (last_backslash) {
        size_t ns_len = last_backslash - str;
        add_assoc_stringl(return_value, "namespace", str, ns_len);

        // Extract class name
        if (last_double_colon && last_double_colon > last_backslash) {
            size_t class_len = last_double_colon - (last_backslash + 1);
            add_assoc_stringl(return_value, "class", last_backslash + 1, class_len);

            // Extract method name
            add_assoc_string(return_value, "method", last_double_colon + 2);
        } else {
            // No method, just class
            add_assoc_string(return_value, "class", last_backslash + 1);
            add_assoc_string(return_value, "method", "");
        }
    } else {
        // No namespace
        add_assoc_string(return_value, "namespace", "");

        if (last_double_colon) {
            size_t class_len = last_double_colon - str;
            add_assoc_stringl(return_value, "class", str, class_len);
            add_assoc_string(return_value, "method", last_double_colon + 2);
        } else {
            add_assoc_string(return_value, "class", str);
            add_assoc_string(return_value, "method", "");
        }
    }
}
/* }}} */

/* }}} */

/* }}} */


/* {{{ arginfo structures */
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_typeset_create, 0, 0, IS_RESOURCE, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_typeset_add, 0, 2, IS_VOID, 0)
    ZEND_ARG_TYPE_INFO(0, typeset, IS_RESOURCE, 0)
    ZEND_ARG_TYPE_INFO(0, object, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_typeset_contains, 0, 2, _IS_BOOL, 0)
    ZEND_ARG_TYPE_INFO(0, typeset, IS_RESOURCE, 0)
    ZEND_ARG_TYPE_INFO(0, object, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_typeset_size, 0, 1, IS_LONG, 0)
    ZEND_ARG_TYPE_INFO(0, typeset, IS_RESOURCE, 0)
ZEND_END_ARG_INFO()


ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_union_can_cast_to, 0, 2, _IS_BOOL, 0)
    ZEND_ARG_TYPE_INFO(0, source_types, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, target_types, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_union_merge_types, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types2, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_union_intersect_types, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types2, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_union_diff_types, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types2, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_array_unique_objects, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, objects, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_array_merge_unique, 0, 2, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, arr1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, arr2, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_array_filter_types, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, types, IS_ARRAY, 0)
ZEND_END_ARG_INFO()


ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_phan_fqsen_parse, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_INFO(0, fqsen, IS_STRING, 0)
ZEND_END_ARG_INFO()

/* }}} */

/* {{{ phan_helper_functions[] */
const zend_function_entry phan_helper_functions[] = {
    PHP_FE(phan_typeset_create,     arginfo_phan_typeset_create)
    PHP_FE(phan_typeset_add,        arginfo_phan_typeset_add)
    PHP_FE(phan_typeset_contains,   arginfo_phan_typeset_contains)
    PHP_FE(phan_typeset_size,       arginfo_phan_typeset_size)

    // Union Type operations
    PHP_FE(phan_union_can_cast_to,  arginfo_phan_union_can_cast_to)
    PHP_FE(phan_union_merge_types,  arginfo_phan_union_merge_types)
    PHP_FE(phan_union_intersect_types, arginfo_phan_union_intersect_types)
    PHP_FE(phan_union_diff_types,   arginfo_phan_union_diff_types)

    // Array operations
    PHP_FE(phan_array_unique_objects, arginfo_phan_array_unique_objects)
    PHP_FE(phan_array_merge_unique, arginfo_phan_array_merge_unique)
    PHP_FE(phan_array_filter_types, arginfo_phan_array_filter_types)

    // String operations
    PHP_FE(phan_fqsen_parse,        arginfo_phan_fqsen_parse)

    PHP_FE_END
};
/* }}} */

/* {{{ phan_helper_module_entry */
zend_module_entry phan_helper_module_entry = {
    STANDARD_MODULE_HEADER,
    "phan_helper",
    phan_helper_functions,
    PHP_MINIT(phan_helper),
    PHP_MSHUTDOWN(phan_helper),
    PHP_RINIT(phan_helper),
    PHP_RSHUTDOWN(phan_helper),
    PHP_MINFO(phan_helper),
    PHP_PHAN_HELPER_VERSION,
    STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_PHAN_HELPER
#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#endif
ZEND_GET_MODULE(phan_helper)
#endif

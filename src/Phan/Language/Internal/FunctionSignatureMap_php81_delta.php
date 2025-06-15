<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
    'array_is_list' => ['bool', 'array'=>'array'],
    'fsync' => ['bool', 'stream'=>'resource'],
    'mysqli_fetch_column' => ['null|int|float|string|false', 'result' => 'mysqli_result', 'column'=>'int'],
    'mysqli_result::fetch_column' => ['null|int|float|string|false', 'column'=>'int'],
  ],

  'changed' => [
    'finfo_buffer' => [
      'old' => ['string|false', 'finfo'=>'resource', 'string'=>'string', 'flags='=>'int', 'context='=>'resource'],
      'new' => ['string|false', 'finfo'=>'finfo', 'string'=>'string', 'flags='=>'int', 'context='=>'resource'],
    ],
    'finfo_close' => [
      'old' => ['bool', 'finfo'=>'resource'],
      'new' => ['bool', 'finfo'=>'finfo'],
    ],
    'finfo_file' => [
      'old' => ['string|false', 'finfo'=>'resource', 'filename'=>'string', 'flags='=>'int', 'context='=>'resource'],
      'new' => ['string|false', 'finfo'=>'finfo', 'filename'=>'string', 'flags='=>'int', 'context='=>'resource'],
    ],
    'finfo_open' => [
      'old' => ['resource|false', 'flags='=>'int', 'magic_database='=>'string'],
      'new' => ['finfo|false', 'flags='=>'int', 'magic_database='=>'string'],
    ],
    'finfo_set_flags' => [
      'old' => ['bool', 'finfo'=>'resource', 'flags'=>'int'],
      'new' => ['bool', 'finfo'=>'finfo', 'flags'=>'int'],
    ],
    'fputcsv' => [
      'old' => ['int|false', 'stream'=>'resource','fields'=>'array','separator='=>'string','enclosure='=>'string','escape='=>'string'],
      'new' => ['int|false', 'stream'=>'resource','fields'=>'array','separator='=>'string','enclosure='=>'string','escape='=>'string','eol='=>'string'],
    ],
    'ftp_alloc' => [
      'old' => ['bool', 'ftp'=>'resource', 'size'=>'int', '&w_response='=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'size'=>'int', '&w_response='=>'string'],
    ],
    'ftp_append' => [
      'old' => ['bool', 'ftp'=>'resource', 'remote_filename'=>'string', 'local_filename'=>'string', 'mode='=>'int'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'remote_filename'=>'string', 'local_filename'=>'string', 'mode='=>'int'],
    ],
    'ftp_cdup' => [
      'old' => ['bool', 'ftp'=>'resource'],
      'new' => ['bool', 'ftp'=>'FTP\Connection'],
    ],
    'ftp_chdir' => [
      'old' => ['bool', 'ftp'=>'resource', 'directory'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'directory'=>'string'],
    ],
    'ftp_chmod' => [
      'old' => ['int|false', 'ftp'=>'resource', 'permissions'=>'int', 'filename'=>'string'],
      'new' => ['int|false', 'ftp'=>'FTP\Connection', 'permissions'=>'int', 'filename'=>'string'],
    ],
    'ftp_close' => [
      'old' => ['bool', 'ftp'=>'resource'],
      'new' => ['bool', 'ftp'=>'FTP\Connection'],
    ],
    'ftp_connect' => [
      'old' => ['resource|false', 'hostname'=>'string', 'port='=>'int', 'timeout='=>'int'],
      'new' => ['FTP\Connection|false', 'hostname'=>'string', 'port='=>'int', 'timeout='=>'int'],
    ],
    'ftp_delete' => [
      'old' => ['bool', 'ftp'=>'resource', 'filename'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'filename'=>'string'],
    ],
    'ftp_exec' => [
      'old' => ['bool', 'ftp'=>'resource', 'command'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'command'=>'string'],
    ],
    'ftp_fget' => [
      'old' => ['bool', 'ftp'=>'resource', 'stream'=>'resource', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'stream'=>'resource', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_fput' => [
      'old' => ['bool', 'ftp'=>'resource', 'remote_filename'=>'string', 'stream'=>'resource', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'remote_filename'=>'string', 'stream'=>'resource', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_get' => [
      'old' => ['bool', 'ftp'=>'resource', 'local_filename'=>'string', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'local_filename'=>'string', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_get_option' => [
      'old' => ['bool|int', 'ftp'=>'resource', 'option'=>'int'],
      'new' => ['bool|int', 'ftp'=>'FTP\Connection', 'option'=>'int'],
    ],
    'ftp_login' => [
      'old' => ['bool', 'ftp'=>'resource', 'username'=>'string', 'password'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'username'=>'string', 'password'=>'string'],
    ],
    'ftp_mdtm' => [
      'old' => ['int', 'ftp'=>'resource', 'filename'=>'string'],
      'new' => ['int', 'ftp'=>'FTP\Connection', 'filename'=>'string'],
    ],
    'ftp_mkdir' => [
      'old' => ['string|false', 'ftp'=>'resource', 'directory'=>'string'],
      'new' => ['string|false', 'ftp'=>'FTP\Connection', 'directory'=>'string'],
    ],
    'ftp_mlsd' => [
      'old' => ['array', 'ftp'=>'resource', 'directory'=>'string'],
      'new' => ['array', 'ftp'=>'FTP\Connection', 'directory'=>'string'],
    ],
    'ftp_nb_continue' => [
      'old' => ['int', 'ftp'=>'resource'],
      'new' => ['int', 'ftp'=>'FTP\Connection'],
    ],
    'ftp_nb_fget' => [
      'old' => ['int', 'ftp'=>'resource', 'stream'=>'resource', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['int', 'ftp'=>'FTP\Connection', 'stream'=>'resource', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_nb_fput' => [
      'old' => ['int', 'ftp'=>'resource', 'remote_filename'=>'string', 'stream'=>'resource', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['int', 'ftp'=>'FTP\Connection', 'remote_filename'=>'string', 'stream'=>'resource', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_nb_get' => [
      'old' => ['int', 'ftp'=>'resource', 'local_filename'=>'string', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['int', 'ftp'=>'FTP\Connection', 'local_filename'=>'string', 'remote_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_nb_put' => [
      'old' => ['int', 'ftp'=>'resource', 'remote_filename'=>'string', 'local_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['int', 'ftp'=>'FTP\Connection', 'remote_filename'=>'string', 'local_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_nlist' => [
      'old' => ['array|false', 'ftp'=>'resource', 'directory'=>'string'],
      'new' => ['array|false', 'ftp'=>'FTP\Connection', 'directory'=>'string'],
    ],
    'ftp_pasv' => [
      'old' => ['bool', 'ftp'=>'resource', 'enable'=>'bool'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'enable'=>'bool'],
    ],
    'ftp_put' => [
      'old' => ['bool', 'ftp'=>'resource', 'remote_filename'=>'string', 'local_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'remote_filename'=>'string', 'local_filename'=>'string', 'mode='=>'int', 'offset='=>'int'],
    ],
    'ftp_pwd' => [
      'old' => ['string|false', 'ftp'=>'resource'],
      'new' => ['string|false', 'ftp'=>'FTP\Connection'],
    ],
    'ftp_quit' => [
      'old' => ['bool', 'ftp'=>'resource'],
      'new' => ['bool', 'ftp'=>'FTP\Connection'],
    ],
    'ftp_raw' => [
      'old' => ['array', 'ftp'=>'resource', 'command'=>'string'],
      'new' => ['array', 'ftp'=>'FTP\Connection', 'command'=>'string'],
    ],
    'ftp_rawlist' => [
      'old' => ['array|false', 'ftp'=>'resource', 'directory'=>'string', 'recursive='=>'bool'],
      'new' => ['array|false', 'ftp'=>'FTP\Connection', 'directory'=>'string', 'recursive='=>'bool'],
    ],
    'ftp_rename' => [
      'old' => ['bool', 'ftp'=>'resource', 'from'=>'string', 'to'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'from'=>'string', 'to'=>'string'],
    ],
    'ftp_rmdir' => [
      'old' => ['bool', 'ftp'=>'resource', 'directory'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'directory'=>'string'],
    ],
    'ftp_set_option' => [
      'old' => ['bool', 'ftp'=>'resource', 'option'=>'int', 'value'=>'bool|int'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'option'=>'int', 'value'=>'bool|int'],
    ],
    'ftp_site' => [
      'old' => ['bool', 'ftp'=>'resource', 'command'=>'string'],
      'new' => ['bool', 'ftp'=>'FTP\Connection', 'command'=>'string'],
    ],
    'ftp_size' => [
      'old' => ['int', 'ftp'=>'resource', 'filename'=>'string'],
      'new' => ['int', 'ftp'=>'FTP\Connection', 'filename'=>'string'],
    ],
    'ftp_ssl_connect' => [
      'old' => ['resource|false', 'hostname'=>'string', 'port='=>'int', 'timeout='=>'int'],
      'new' => ['FTP\Connection|false', 'hostname'=>'string', 'port='=>'int', 'timeout='=>'int'],
    ],
    'ftp_systype' => [
      'old' => ['string|false', 'ftp'=>'resource'],
      'new' => ['string|false', 'ftp'=>'FTP\Connection'],
    ],
    'imap_append' => [
      'old' => ['bool', 'imap'=>'resource', 'folder'=>'string', 'message'=>'string', 'options='=>'string', 'internal_date='=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'folder'=>'string', 'message'=>'string', 'options='=>'string', 'internal_date='=>'string'],
    ],
    'imap_body' => [
      'old' => ['string|false', 'imap'=>'resource', 'message_num'=>'int', 'flags='=>'int'],
      'new' => ['string|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'flags='=>'int'],
    ],
    'imap_bodystruct' => [
      'old' => ['stdClass|false', 'imap'=>'resource', 'message_num'=>'int', 'section'=>'string'],
      'new' => ['stdClass|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'section'=>'string'],
    ],
    'imap_check' => [
      'old' => ['stdClass|false', 'imap'=>'resource'],
      'new' => ['stdClass|false', 'imap'=>'IMAP\Connection'],
    ],
    'imap_clearflag_full' => [
      'old' => ['bool', 'imap'=>'resource', 'sequence'=>'string', 'flag'=>'string', 'options='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'sequence'=>'string', 'flag'=>'string', 'options='=>'int'],
    ],
    'imap_close' => [
      'old' => ['bool', 'imap'=>'resource', 'flags='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'flags='=>'int'],
    ],
    'imap_create' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'imap_createmailbox' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'imap_delete' => [
      'old' => ['bool', 'imap'=>'resource', 'message_num'=>'string', 'flags='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'message_num'=>'string', 'flags='=>'int'],
    ],
    'imap_deletemailbox' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'imap_expunge' => [
      'old' => ['bool', 'imap'=>'resource'],
      'new' => ['bool', 'imap'=>'IMAP\Connection'],
    ],
    'imap_fetch_overview' => [
      'old' => ['array|false', 'imap'=>'resource', 'sequence'=>'string', 'flags='=>'int'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'sequence'=>'string', 'flags='=>'int'],
    ],
    'imap_fetchbody' => [
      'old' => ['string|false', 'imap'=>'resource', 'message_num'=>'int', 'section'=>'string', 'flags='=>'int'],
      'new' => ['string|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'section'=>'string', 'flags='=>'int'],
    ],
    'imap_fetchheader' => [
      'old' => ['string|false', 'imap'=>'resource', 'message_num'=>'int', 'flags='=>'int'],
      'new' => ['string|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'flags='=>'int'],
    ],
    'imap_fetchmime' => [
      'old' => ['string|false', 'imap'=>'resource', 'message_num'=>'int', 'section'=>'string', 'flags='=>'int'],
      'new' => ['string|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'section'=>'string', 'flags='=>'int'],
    ],
    'imap_fetchstructure' => [
      'old' => ['stdClass|false', 'imap'=>'resource', 'message_num'=>'int', 'flags='=>'int'],
      'new' => ['stdClass|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'flags='=>'int'],
    ],
    'imap_fetchtext' => [
      'old' => ['string|false', 'imap'=>'resource', 'message_num'=>'int', 'flags='=>'int'],
      'new' => ['string|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'flags='=>'int'],
    ],
    'imap_gc' => [
      'old' => ['bool', 'imap'=>'resource', 'flags'=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'flags'=>'int'],
    ],
    'imap_get_quota' => [
      'old' => ['array|false', 'imap'=>'resource', 'quota_root'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'quota_root'=>'string'],
    ],
    'imap_get_quotaroot' => [
      'old' => ['array|false', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'imap_getacl' => [
      'old' => ['array|false', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'imap_getmailboxes' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string'],
    ],
    'imap_getsubscribed' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string'],
    ],
    'imap_headerinfo' => [
      'old' => ['stdClass|false', 'imap'=>'resource', 'message_num'=>'int', 'from_length='=>'int', 'subject_length='=>'int', 'default_host='=>'string'],
      'new' => ['stdClass|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int', 'from_length='=>'int', 'subject_length='=>'int', 'default_host='=>'string'],
    ],
    'imap_headers' => [
      'old' => ['array|false', 'imap'=>'resource'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection'],
    ],
    'imap_list' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string'],
    ],
    'imap_listmailbox' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string'],
    ],
    'imap_listscan' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string', 'content'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string', 'content'=>'string'],
    ],
    'imap_listsubscribed' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string'],
    ],
    'imap_lsub' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string'],
    ],
    'imap_mail_copy' => [
      'old' => ['bool', 'imap'=>'resource', 'message_nums'=>'string', 'mailbox'=>'string', 'flags='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'message_nums'=>'string', 'mailbox'=>'string', 'flags='=>'int'],
    ],
    'imap_mail_move' => [
      'old' => ['bool', 'imap'=>'resource', 'message_nums'=>'string', 'mailbox'=>'string', 'flags='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'message_nums'=>'string', 'mailbox'=>'string', 'flags='=>'int'],
    ],
    'imap_mailboxmsginfo' => [
      'old' => ['stdClass', 'imap'=>'resource'],
      'new' => ['stdClass', 'imap'=>'IMAP\Connection'],
    ],
    'imap_msgno' => [
      'old' => ['int|false', 'imap'=>'resource', 'message_uid'=>'int'],
      'new' => ['int|false', 'imap'=>'IMAP\Connection', 'message_uid'=>'int'],
    ],
    'imap_num_msg' => [
      'old' => ['int|false', 'imap'=>'resource'],
      'new' => ['int|false', 'imap'=>'IMAP\Connection'],
    ],
    'imap_num_recent' => [
      'old' => ['int|false', 'imap'=>'resource'],
      'new' => ['int|false', 'imap'=>'IMAP\Connection'],
    ],
    'imap_open' => [
      'old' => ['resource|false', 'mailbox'=>'string', 'user'=>'string', 'password'=>'string', 'flags='=>'int', 'retries='=>'int', 'options='=>'array'],
      'new' => ['IMAP\Connection|false', 'mailbox'=>'string', 'user'=>'string', 'password'=>'string', 'flags='=>'int', 'retries='=>'int', 'options='=>'array'],
    ],
    'imap_ping' => [
      'old' => ['bool', 'imap'=>'resource'],
      'new' => ['bool', 'imap'=>'IMAP\Connection'],
    ],
    'imap_rename' => [
      'old' => ['bool', 'imap'=>'resource', 'from'=>'string', 'to'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'from'=>'string', 'to'=>'string'],
    ],
    'imap_renamemailbox' => [
      'old' => ['bool', 'imap'=>'resource', 'from'=>'string', 'to'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'from'=>'string', 'to'=>'string'],
    ],
    'imap_reopen' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string', 'flags='=>'int', 'retries='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string', 'flags='=>'int', 'retries='=>'int'],
    ],
    'imap_savebody' => [
      'old' => ['bool', 'imap'=>'resource', 'file'=>'string|resource', 'message_num'=>'int', 'section='=>'string', 'flags='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'file'=>'string|resource', 'message_num'=>'int', 'section='=>'string', 'flags='=>'int'],
    ],
    'imap_scan' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string', 'content'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string', 'content'=>'string'],
    ],
    'imap_scanmailbox' => [
      'old' => ['array|false', 'imap'=>'resource', 'reference'=>'string', 'pattern'=>'string', 'content'=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'reference'=>'string', 'pattern'=>'string', 'content'=>'string'],
    ],
    'imap_search' => [
      'old' => ['array|false', 'imap'=>'resource', 'criteria'=>'string', 'flags='=>'int', 'charset='=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'criteria'=>'string', 'flags='=>'int', 'charset='=>'string'],
    ],
    'imap_set_quota' => [
      'old' => ['bool', 'imap'=>'resource', 'quota_root'=>'string', 'mailbox_size'=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'quota_root'=>'string', 'mailbox_size'=>'int'],
    ],
    'imap_setacl' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string', 'user_id'=>'string', 'rights'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string', 'user_id'=>'string', 'rights'=>'string'],
    ],
    'imap_setflag_full' => [
      'old' => ['bool', 'imap'=>'resource', 'sequence'=>'string', 'flag'=>'string', 'options='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'sequence'=>'string', 'flag'=>'string', 'options='=>'int'],
    ],
    'imap_sort' => [
      'old' => ['array|false', 'imap'=>'resource', 'criteria'=>'int', 'reverse'=>'bool', 'flags='=>'int', 'search_criteria='=>'string', 'charset='=>'string'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'criteria'=>'int', 'reverse'=>'bool', 'flags='=>'int', 'search_criteria='=>'string', 'charset='=>'string'],
    ],
    'imap_status' => [
      'old' => ['stdClass|false', 'imap'=>'resource', 'mailbox'=>'string', 'flags'=>'int'],
      'new' => ['stdClass|false', 'imap'=>'IMAP\Connection', 'mailbox'=>'string', 'flags'=>'int'],
    ],
    'imap_subscribe' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'imap_thread' => [
      'old' => ['array|false', 'imap'=>'resource', 'flags='=>'int'],
      'new' => ['array|false', 'imap'=>'IMAP\Connection', 'flags='=>'int'],
    ],
    'imap_uid' => [
      'old' => ['int|false', 'imap'=>'resource', 'message_num'=>'int'],
      'new' => ['int|false', 'imap'=>'IMAP\Connection', 'message_num'=>'int'],
    ],
    'imap_undelete' => [
      'old' => ['bool', 'imap'=>'resource', 'message_num'=>'string', 'flags='=>'int'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'message_num'=>'string', 'flags='=>'int'],
    ],
    'imap_unsubscribe' => [
      'old' => ['bool', 'imap'=>'resource', 'mailbox'=>'string'],
      'new' => ['bool', 'imap'=>'IMAP\Connection', 'mailbox'=>'string'],
    ],
    'ini_set' => [
      'old' => ['string|false', 'option'=>'string', 'value'=>'string'],
      'new' => ['string|false', 'option'=>'string', 'value'=>'string|int|float|bool|null'],
    ],
    'ldap_add' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_add_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_bind' => [
      'old' => ['bool', 'ldap'=>'resource','dn='=>'string','password='=>'?string','serverctls='=>'?array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn='=>'string','password='=>'?string','serverctls='=>'?array'],
    ],
    'ldap_bind_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn='=>'string','password='=>'?string','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn='=>'string','password='=>'?string','controls='=>'?array'],
    ],
    'ldap_close' => [
      'old' => ['bool', 'ldap'=>'resource'],
      'new' => ['bool', 'ldap'=>'ldap\connection'],
    ],
    'ldap_compare' => [
      'old' => ['bool|int', 'ldap'=>'resource','dn'=>'string','attribute'=>'string','value'=>'string','controls='=>'?array'],
      'new' => ['bool|int', 'ldap'=>'ldap\connection','dn'=>'string','attribute'=>'string','value'=>'string','controls='=>'?array'],
    ],
    'ldap_connect' => [
      'old' => ['resource|false', 'uri='=>'string','port='=>'int','wallet='=>'string','password='=>'string','auth_mode='=>'int'],
      'new' => ['ldap\connection|false', 'uri='=>'string','port='=>'int','wallet='=>'string','password='=>'string','auth_mode='=>'int'],
    ],
    'ldap_count_entries' => [
      'old' => ['int', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['int', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_delete' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string'],
    ],
    'ldap_delete_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','controls='=>'?array'],
    ],
    'ldap_errno' => [
      'old' => ['int', 'ldap'=>'resource'],
      'new' => ['int', 'ldap'=>'ldap\connection'],
    ],
    'ldap_error' => [
      'old' => ['string', 'ldap'=>'resource'],
      'new' => ['string', 'ldap'=>'ldap\connection'],
    ],
    'ldap_exop' => [
      'old' => ['bool|resource', 'ldap'=>'resource','request_oid'=>'string','request_data='=>'string','controls='=>'?array','&w_response_data='=>'string','&w_response_oid='=>'string'],
      'new' => ['bool|ldap\result', 'ldap'=>'ldap\connection','request_oid'=>'string','request_data='=>'string','controls='=>'?array','&w_response_data='=>'string','&w_response_oid='=>'string'],
    ],
    'ldap_exop_passwd' => [
      'old' => ['bool|string', 'ldap'=>'resource','user='=>'string','old_password='=>'string','new_password='=>'string','&controls='=>'?array'],
      'new' => ['bool|string', 'ldap'=>'ldap\connection','user='=>'string','old_password='=>'string','new_password='=>'string','&controls='=>'?array'],
    ],
    'ldap_exop_refresh' => [
      'old' => ['int|false', 'ldap'=>'resource','dn'=>'string','ttl'=>'int'],
      'new' => ['int|false', 'ldap'=>'ldap\connection','dn'=>'string','ttl'=>'int'],
    ],
    'ldap_exop_whoami' => [
      'old' => ['string|false', 'ldap'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection'],
    ],
    'ldap_first_attribute' => [
      'old' => ['string|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_first_entry' => [
      'old' => ['resource|false', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_first_reference' => [
      'old' => ['resource|false', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_free_result' => [
      'old' => ['bool', 'result'=>'resource'],
      'new' => ['bool', 'result'=>'ldap\result'],
    ],
    'ldap_get_attributes' => [
      'old' => ['array', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['array', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_get_dn' => [
      'old' => ['string|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_get_entries' => [
      'old' => ['array|false', 'ldap'=>'resource','result'=>'resource'],
      'new' => ['array|false', 'ldap'=>'ldap\connection','result'=>'ldap\result'],
    ],
    'ldap_get_option' => [
      'old' => ['bool', 'ldap'=>'resource','option'=>'int','&w_value='=>'array|int|string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','option'=>'int','&w_value='=>'array|int|string'],
    ],
    'ldap_get_values' => [
      'old' => ['array|false', 'ldap'=>'resource','entry'=>'resource','attribute'=>'string'],
      'new' => ['array|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry','attribute'=>'string'],
    ],
    'ldap_get_values_len' => [
      'old' => ['array|false', 'ldap'=>'resource','entry'=>'resource','attribute'=>'string'],
      'new' => ['array|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry','attribute'=>'string'],
    ],
    'ldap_list' => [
      'old' => ['resource|false', 'ldap'=>'resource|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
      'new' => ['ldap\result|array|false', 'ldap'=>'ldap\connection|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
    ],
    'ldap_mod_add' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_mod_add_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_mod_del' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_mod_del_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_mod_replace' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_mod_replace_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','entry'=>'array','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array','controls='=>'?array'],
    ],
    'ldap_modify' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','entry'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','entry'=>'array'],
    ],
    'ldap_modify_batch' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','modifications_info'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','modifications_info'=>'array'],
    ],
    'ldap_next_attribute' => [
      'old' => ['string|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['string|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_next_entry' => [
      'old' => ['resource|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_next_reference' => [
      'old' => ['resource|false', 'ldap'=>'resource','entry'=>'resource'],
      'new' => ['ldap\resultentry|false', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry'],
    ],
    'ldap_parse_exop' => [
      'old' => ['bool', 'ldap'=>'resource','result'=>'resource','&w_response_data='=>'string','&w_response_oid='=>'string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','result'=>'ldap\result','&w_response_data='=>'string','&w_response_oid='=>'string'],
    ],
    'ldap_parse_reference' => [
      'old' => ['bool', 'ldap'=>'resource','entry'=>'resource','&referrals'=>'array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','entry'=>'ldap\resultentry','&referrals'=>'array'],
    ],
    'ldap_parse_result' => [
      'old' => ['bool', 'ldap'=>'resource','result'=>'resource','&w_error_code'=>'int','&w_matched_dn='=>'string','&w_error_message='=>'string','&w_referrals='=>'array','&w_controls='=>'?array'],
      'new' => ['bool', 'ldap'=>'ldap\connection','result'=>'ldap\result','&w_error_code'=>'int','&w_matched_dn='=>'string','&w_error_message='=>'string','&w_referrals='=>'array','&w_controls='=>'?array'],
    ],
    'ldap_read' => [
      'old' => ['resource|false', 'ldap'=>'resource|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
      'new' => ['ldap\result|array|false', 'ldap'=>'ldap\connection|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int'],
    ],
    'ldap_rename' => [
      'old' => ['bool', 'ldap'=>'resource','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool'],
    ],
    'ldap_rename_ext' => [
      'old' => ['resource|false', 'ldap'=>'resource','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool','controls='=>'?array'],
      'new' => ['ldap\result|false', 'ldap'=>'ldap\connection','dn'=>'string','new_rdn'=>'string','new_parent'=>'string','delete_old_rdn'=>'bool','controls='=>'?array'],
    ],
    'ldap_sasl_bind' => [
      'old' => ['bool', 'ldap'=>'resource','dn='=>'string','password='=>'string','mech='=>'string','realm='=>'string','authc_id='=>'string','authz_id='=>'string','props='=>'string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','dn='=>'string','password='=>'string','mech='=>'string','realm='=>'string','authc_id='=>'string','authz_id='=>'string','props='=>'string'],
    ],
    'ldap_search' => [
      'old' => ['resource|false', 'ldap'=>'resource|resource[]','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int','controls='=>'?array'],
      'new' => ['ldap\result|array|false', 'ldap'=>'ldap\connection|array','base'=>'string','filter'=>'string','attributes='=>'array','attributes_only='=>'int','sizelimit='=>'int','timelimit='=>'int','deref='=>'int','controls='=>'?array'],
    ],
    'ldap_set_option' => [
      'old' => ['bool', 'ldap'=>'resource','option'=>'int','value'=>'array|bool|int|string'],
      'new' => ['bool', 'ldap'=>'ldap\connection','option'=>'int','value'=>'array|bool|int|string'],
    ],
    'ldap_set_rebind_proc' => [
      'old' => ['bool', 'ldap'=>'resource','callback'=>'?callable'],
      'new' => ['bool', 'ldap'=>'ldap\connection','callback'=>'?callable'],
    ],
    'ldap_start_tls' => [
      'old' => ['bool', 'ldap'=>'resource'],
      'new' => ['bool', 'ldap'=>'ldap\connection'],
    ],
    'ldap_unbind' => [
      'old' => ['bool', 'ldap'=>'resource'],
      'new' => ['bool', 'ldap'=>'ldap\connection'],
    ],

    'mysqli_stmt::execute' => [
      'old' => ['bool'],
      'new' => ['bool', 'params=' => '?list<string>'],
    ],
    'mysqli_stmt_execute' => [
      'old' => ['bool', 'statement'=>'mysqli_stmt'],
      'new' => ['bool', 'statement'=>'mysqli_stmt', 'params=' => '?list<string>'],
    ],

    'pg_affected_rows' => [
      'old' => ['int', 'result'=>'resource'],
      'new' => ['int', 'result'=>'pgsql\result'],
    ],
    'pg_cancel_query' => [
      'old' => ['bool', 'connection'=>'resource'],
      'new' => ['bool', 'connection'=>'pgsql\connection'],
    ],
    'pg_client_encoding' => [
      'old' => ['string', 'connection='=>'?resource'],
      'new' => ['string', 'connection='=>'?pgsql\connection'],
    ],
    'pg_close' => [
      'old' => ['bool', 'connection='=>'?resource'],
      'new' => ['bool', 'connection='=>'?pgsql\connection'],
    ],
    'pg_connect' => [
      'old' => ['resource|false', 'connection_string'=>'string', 'flags='=>'int'],
      'new' => ['pgsql\connection|false', 'connection_string'=>'string', 'flags='=>'int'],
    ],
    'pg_connect_poll' => [
      'old' => ['int', 'connection'=>'resource'],
      'new' => ['int', 'connection'=>'pgsql\connection'],
    ],
    'pg_connection_busy' => [
      'old' => ['bool', 'connection'=>'resource'],
      'new' => ['bool', 'connection'=>'pgsql\connection'],
    ],
    'pg_connection_reset' => [
      'old' => ['bool', 'connection'=>'resource'],
      'new' => ['bool', 'connection'=>'pgsql\connection'],
    ],
    'pg_connection_status' => [
      'old' => ['int', 'connection'=>'resource'],
      'new' => ['int', 'connection'=>'pgsql\connection'],
    ],
    'pg_consume_input' => [
      'old' => ['bool', 'connection'=>'resource'],
      'new' => ['bool', 'connection'=>'pgsql\connection'],
    ],
    'pg_convert' => [
      'old' => ['array<string,mixed>', 'connection'=>'resource', 'table_name'=>'string', 'values'=>'array', 'flags='=>'int'],
      'new' => ['array<string,mixed>', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'values'=>'array', 'flags='=>'int'],
    ],
    'pg_copy_from' => [
      'old' => ['bool', 'connection'=>'resource', 'table_name'=>'string', 'rows'=>'array', 'separator='=>'string', 'null_as='=>'string'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'rows'=>'array', 'separator='=>'string', 'null_as='=>'string'],
    ],
    'pg_copy_to' => [
      'old' => ['array<int,string>|false', 'connection'=>'resource', 'table_name'=>'string', 'separator='=>'string', 'null_as='=>'string'],
      'new' => ['array<int,string>|false', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'separator='=>'string', 'null_as='=>'string'],
    ],
    'pg_dbname' => [
      'old' => ['string', 'connection='=>'resource'],
      'new' => ['string', 'connection='=>'?pgsql\connection'],
    ],
    'pg_delete' => [
      'old' => ['string|bool', 'connection'=>'resource', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int'],
      'new' => ['string|bool', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int'],
    ],
    'pg_end_copy' => [
      'old' => ['bool', 'connection='=>'resource'],
      'new' => ['bool', 'connection='=>'pgsql\connection'],
    ],
    'pg_escape_bytea' => [
      'old' => ['string', 'connection'=>'resource', 'string'=>'string'],
      'new' => ['string', 'connection'=>'pgsql\connection', 'string'=>'string'],
    ],
    'pg_escape_identifier' => [
      'old' => ['string', 'connection'=>'resource', 'string'=>'string'],
      'new' => ['string', 'connection'=>'pgsql\connection', 'string'=>'string'],
    ],
    'pg_escape_literal' => [
      'old' => ['string', 'connection'=>'resource', 'string'=>'string'],
      'new' => ['string', 'connection'=>'pgsql\connection', 'string'=>'string'],
    ],
    'pg_escape_string' => [
      'old' => ['string', 'connection'=>'resource', 'string'=>'string'],
      'new' => ['string', 'connection'=>'pgsql\connection', 'string'=>'string'],
    ],
    'pg_execute' => [
      'old' => ['resource|false', 'connection'=>'resource', 'statement_name'=>'string', 'params'=>'array'],
      'new' => ['pgsql\result|false', 'connection'=>'pgsql\connection', 'statement_name'=>'string', 'params'=>'array'],
    ],
    'pg_execute\'1' => [
      'old' => ['resource|false', 'connection'=>'string', 'statement_name'=>'array'],
      'new' => ['pgsql\result|false', 'connection'=>'string', 'statement_name'=>'array'],
    ],
    'pg_fetch_all' => [
      'old' => ['array<int,array>', 'result'=>'resource', 'mode='=>'int'],
      'new' => ['array<int,array>', 'result'=>'pgsql\result', 'mode='=>'int'],
    ],
    'pg_fetch_all_columns' => [
      'old' => ['array<int,?string>', 'result'=>'resource', 'field='=>'int'],
      'new' => ['array<int,?string>', 'result'=>'pgsql\result', 'field='=>'int'],
    ],
    'pg_fetch_array' => [
      'old' => ['string[]|null[]|false', 'result'=>'resource', 'row='=>'?int', 'mode='=>'int'],
      'new' => ['string[]|null[]|false', 'result'=>'pgsql\result', 'row='=>'?int', 'mode='=>'int'],
    ],
    'pg_fetch_assoc' => [
      'old' => ['array<string,string|null>|false', 'result'=>'resource', 'row='=>'?int'],
      'new' => ['array<string,string|null>|false', 'result'=>'pgsql\result', 'row='=>'?int'],
    ],
    'pg_fetch_object' => [
      'old' => ['object', 'result'=>'resource', 'row='=>'?int', 'class='=>'int'],
      'new' => ['object', 'result'=>'pgsql\result', 'row='=>'?int', 'class='=>'int'],
    ],
    'pg_fetch_object\'1' => [
      'old' => ['object', 'result'=>'resource', 'row='=>'?int', 'class='=>'string', 'constructor_args='=>'array'],
      'new' => ['object', 'result'=>'pgsql\result', 'row='=>'?int', 'class='=>'string', 'constructor_args='=>'array'],
    ],
    'pg_fetch_result' => [
      'old' => ['string', 'result'=>'resource', 'row'=>'string|int'],
      'new' => ['string', 'result'=>'pgsql\result', 'row'=>'string|int'],
    ],
    'pg_fetch_result\'1' => [
      'old' => ['string', 'result'=>'resource', 'row'=>'?int', 'field'=>'string|int'],
      'new' => ['string', 'result'=>'pgsql\result', 'row'=>'?int', 'field'=>'string|int'],
    ],
    'pg_fetch_row' => [
      'old' => ['array<int,string|null>', 'result'=>'resource', 'row='=>'?int', 'mode='=>'int'],
      'new' => ['array<int,string|null>', 'result'=>'pgsql\result', 'row='=>'?int', 'mode='=>'int'],
    ],
    'pg_field_is_null' => [
      'old' => ['int', 'result'=>'resource', 'row'=>'string|int'],
      'new' => ['int', 'result'=>'pgsql\result', 'row'=>'string|int'],
    ],
    'pg_field_is_null\'1' => [
      'old' => ['int', 'result'=>'resource', 'row'=>'int', 'field'=>'string|int'],
      'new' => ['int', 'result'=>'pgsql\result', 'row'=>'int', 'field'=>'string|int'],
    ],
    'pg_field_name' => [
      'old' => ['string', 'result'=>'resource', 'field'=>'int'],
      'new' => ['string', 'result'=>'pgsql\result', 'field'=>'int'],
    ],
    'pg_field_num' => [
      'old' => ['int', 'result'=>'resource', 'field'=>'string'],
      'new' => ['int', 'result'=>'pgsql\result', 'field'=>'string'],
    ],
    'pg_field_prtlen' => [
      'old' => ['int|false', 'result'=>'resource', 'row'=>''],
      'new' => ['int|false', 'result'=>'pgsql\result', 'row'=>''],
    ],
    'pg_field_prtlen\'1' => [
      'old' => ['int', 'result'=>'resource', 'row'=>'int', 'field'=>'string|int'],
      'new' => ['int', 'result'=>'pgsql\result', 'row'=>'int', 'field'=>'string|int'],
    ],
    'pg_field_size' => [
      'old' => ['int', 'result'=>'resource', 'field'=>'int'],
      'new' => ['int', 'result'=>'pgsql\result', 'field'=>'int'],
    ],
    'pg_field_table' => [
      'old' => ['false|int|string', 'result'=>'resource', 'field'=>'int', 'oid_only='=>'bool'],
      'new' => ['false|int|string', 'result'=>'pgsql\result', 'field'=>'int', 'oid_only='=>'bool'],
    ],
    'pg_field_type' => [
      'old' => ['string', 'result'=>'resource', 'field'=>'int'],
      'new' => ['string', 'result'=>'pgsql\result', 'field'=>'int'],
    ],
    'pg_field_type_oid' => [
      'old' => ['int', 'result'=>'resource', 'field'=>'int'],
      'new' => ['int', 'result'=>'pgsql\result', 'field'=>'int'],
    ],
    'pg_flush' => [
      'old' => ['bool|int', 'connection'=>'resource'],
      'new' => ['bool|int', 'connection'=>'pgsql\result'],
    ],
    'pg_free_result' => [
      'old' => ['bool', 'result'=>'resource'],
      'new' => ['bool', 'result'=>'pgsql\result'],
    ],
    'pg_get_notify' => [
      'old' => ['array|false', 'connection'=>'resource', 'mode='=>'int'],
      'new' => ['array|false', 'connection'=>'pgsql\connection', 'mode='=>'int'],
    ],
    'pg_get_pid' => [
      'old' => ['int', 'connection'=>'resource'],
      'new' => ['int', 'connection'=>'pgsql\connection'],
    ],
    'pg_get_result' => [
      'old' => ['resource|false', 'connection'=>'resource'],
      'new' => ['pgsql\result|false', 'connection'=>'pgsql\connection'],
    ],
    'pg_host' => [
      'old' => ['string', 'connection='=>'resource'],
      'new' => ['string', 'connection='=>'pgsql\connection'],
    ],
    'pg_insert' => [
      'old' => ['resource|string|false', 'connection'=>'resource', 'table_name'=>'string', 'values'=>'array', 'flags='=>'int'],
      'new' => ['pgsql\result|string|bool', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'values'=>'array', 'flags='=>'int'],
    ],
    'pg_last_error' => [
      'old' => ['string', 'connection='=>'resource', 'operation='=>'int'],
      'new' => ['string', 'connection='=>'pgsql\connection', 'operation='=>'int'],
    ],
    'pg_last_notice' => [
      'old' => ['string|array|bool', 'connection'=>'resource', 'mode='=>'int'],
      'new' => ['string|array|bool', 'connection'=>'pgsql\connection', 'mode='=>'int'],
    ],
    'pg_last_oid' => [
      'old' => ['string', 'result'=>'resource'],
      'new' => ['string', 'result'=>'pgsql\result'],
    ],
    'pg_lo_close' => [
      'old' => ['bool', 'lob'=>'resource'],
      'new' => ['bool', 'lob'=>'pgsql\lob'],
    ],
    'pg_lo_create' => [
      'old' => ['int|false', 'connection='=>'resource', 'oid='=>''],
      'new' => ['int|false', 'connection='=>'pgsql\connection', 'oid='=>''],
    ],
    'pg_lo_export' => [
      'old' => ['bool', 'connection'=>'resource', 'oid'=>'int', 'filename'=>'string'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'oid'=>'int', 'filename'=>'string'],
    ],
    'pg_lo_import' => [
      'old' => ['int', 'connection'=>'resource', 'filename'=>'string', 'oid'=>''],
      'new' => ['int', 'connection'=>'pgsql\connection', 'filename'=>'string', 'oid'=>''],
    ],
    'pg_lo_open' => [
      'old' => ['resource|false', 'connection'=>'resource', 'oid='=>'int', 'mode='=>'string'],
      'new' => ['pgsql\lob|false', 'connection'=>'pgsql\connection', 'oid='=>'int', 'mode='=>'string'],
    ],
    'pg_lo_read' => [
      'old' => ['string', 'lob'=>'resource', 'length='=>'int'],
      'new' => ['string', 'lob'=>'pgsql\lob', 'length='=>'int'],
    ],
    'pg_lo_read_all' => [
      'old' => ['int', 'lob'=>'resource'],
      'new' => ['int', 'lob'=>'pgsql\lob'],
    ],
    'pg_lo_seek' => [
      'old' => ['bool', 'lob'=>'resource', 'offset'=>'int', 'whence='=>'int'],
      'new' => ['bool', 'lob'=>'pgsql\lob', 'offset'=>'int', 'whence='=>'int'],
    ],
    'pg_lo_tell' => [
      'old' => ['int', 'lob'=>'resource'],
      'new' => ['int', 'lob'=>'pgsql\lob'],
    ],
    'pg_lo_truncate' => [
      'old' => ['bool', 'lob'=>'resource', 'size'=>'int'],
      'new' => ['bool', 'lob'=>'pgsql\lob', 'size'=>'int'],
    ],
    'pg_lo_unlink' => [
      'old' => ['bool', 'connection'=>'resource', 'oid='=>'int'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'oid='=>'int'],
    ],
    'pg_lo_write' => [
      'old' => ['int|false', 'lob'=>'resource', 'data'=>'string', 'length='=>'int'],
      'new' => ['int|false', 'lob'=>'pgsql\lob', 'data'=>'string', 'length='=>'int'],
    ],
    'pg_meta_data' => [
      'old' => ['array<string,array>', 'connection'=>'resource', 'table_name'=>'string', 'extended='=>'bool'],
      'new' => ['array<string,array>', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'extended='=>'bool'],
    ],
    'pg_num_fields' => [
      'old' => ['int', 'result'=>'resource'],
      'new' => ['int', 'result'=>'pgsql\result'],
    ],
    'pg_num_rows' => [
      'old' => ['int', 'result'=>'resource'],
      'new' => ['int', 'result'=>'pgsql\result'],
    ],
    'pg_options' => [
      'old' => ['string', 'connection='=>'resource'],
      'new' => ['string', 'connection='=>'pgsql\connection'],
    ],
    'pg_parameter_status' => [
      'old' => ['string|false', 'connection'=>'resource', 'name'=>'string'],
      'new' => ['string|false', 'connection'=>'pgsql\connection', 'name'=>'string'],
    ],
    'pg_pconnect' => [
      'old' => ['resource|false', 'connection_string'=>'string', 'flags='=>'int'],
      'new' => ['pgsql\connection|false', 'connection_string'=>'string', 'flags='=>'int'],
    ],
    'pg_ping' => [
      'old' => ['bool', 'connection='=>'resource'],
      'new' => ['bool', 'connection='=>'pgsql\connection'],
    ],
    'pg_port' => [
      'old' => ['string', 'connection='=>'resource'],
      'new' => ['string', 'connection='=>'pgsql\connection'],
    ],
    'pg_prepare' => [
      'old' => ['resource|false', 'connection'=>'resource', 'statement_name'=>'string', 'query'=>'string'],
      'new' => ['pgsql\result|false', 'connection'=>'pgsql\connection', 'statement_name'=>'string', 'query'=>'string'],
    ],
    'pg_prepare\'1' => [
      'old' => ['resource|false', 'connection'=>'string', 'statement_name'=>'string'],
      'new' => ['pgsql\result|false', 'connection'=>'string', 'statement_name'=>'string'],
    ],
    'pg_put_line' => [
      'old' => ['bool', 'connection'=>'resource', 'query'=>'string'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'query'=>'string'],
    ],
    'pg_query' => [
      'old' => ['resource|false', 'connection'=>'resource', 'query'=>'string'],
      'new' => ['pgsql\result|false', 'connection'=>'pgsql\connection', 'query'=>'string'],
    ],
    'pg_query\'1' => [
      'old' => ['resource|false', 'connection'=>'string'],
      'new' => ['pgsql\result|false', 'connection'=>'string'],
    ],
    'pg_query_params' => [
      'old' => ['resource|false', 'connection'=>'resource', 'query'=>'string', 'params'=>'array'],
      'new' => ['pgsql\result|false', 'connection'=>'pgsql\connection', 'query'=>'string', 'params'=>'array'],
    ],
    'pg_query_params\'1' => [
      'old' => ['resource|false', 'connection'=>'string', 'query'=>'array'],
      'new' => ['pgsql\result|false', 'connection'=>'string', 'query'=>'array'],
    ],
    'pg_result_error' => [
      'old' => ['string|false', 'result'=>'resource'],
      'new' => ['string|false', 'result'=>'pgsql\result'],
    ],
    'pg_result_error_field' => [
      'old' => ['string|?false', 'result'=>'resource', 'field_code'=>'int'],
      'new' => ['string|?false', 'result'=>'pgsql\result', 'field_code'=>'int'],
    ],
    'pg_result_seek' => [
      'old' => ['bool', 'result'=>'resource', 'row'=>'int'],
      'new' => ['bool', 'result'=>'pgsql\result', 'row'=>'int'],
    ],
    'pg_result_status' => [
      'old' => ['int|string', 'result'=>'resource', 'mode='=>'int'],
      'new' => ['int|string', 'result'=>'pgsql\result', 'mode='=>'int'],
    ],
    'pg_select' => [
      'old' => ['string|bool', 'connection'=>'resource', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int', 'mode='=>'int'],
      'new' => ['string|bool', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int', 'mode='=>'int'],
    ],
    'pg_send_execute' => [
      'old' => ['bool', 'connection'=>'resource', 'query'=>'string', 'params'=>'array'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'query'=>'string', 'params'=>'array'],
    ],
    'pg_send_prepare' => [
      'old' => ['bool', 'connection'=>'resource', 'statement_name'=>'string', 'query'=>'string'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'statement_name'=>'string', 'query'=>'string'],
    ],
    'pg_send_query' => [
      'old' => ['bool', 'connection'=>'resource', 'query'=>'string'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'query'=>'string'],
    ],
    'pg_send_query_params' => [
      'old' => ['bool', 'connection'=>'resource', 'query'=>'string', 'params'=>'array'],
      'new' => ['bool', 'connection'=>'pgsql\connection', 'query'=>'string', 'params'=>'array'],
    ],
    'pg_set_client_encoding' => [
      'old' => ['int', 'connection'=>'resource', 'encoding'=>'string'],
      'new' => ['int', 'connection'=>'pgsql\connection', 'encoding'=>'string'],
    ],
    'pg_set_error_verbosity' => [
      'old' => ['int', 'connection'=>'resource', 'verbosity'=>'int'],
      'new' => ['int', 'connection'=>'pgsql\connection', 'verbosity'=>'int'],
    ],
    'pg_socket' => [
      'old' => ['resource|false', 'connection'=>'resource'],
      'new' => ['resource|false', 'connection'=>'pgsql\connection'],
    ],
    'pg_trace' => [
      'old' => ['bool', 'filename'=>'string', 'mode='=>'string', 'connection='=>'resource'],
      'new' => ['bool', 'filename'=>'string', 'mode='=>'string', 'connection='=>'pgsql\connection'],
    ],
    'pg_transaction_status' => [
      'old' => ['int', 'connection'=>'resource'],
      'new' => ['int', 'connection'=>'pgsql\connection'],
    ],
    'pg_tty' => [
      'old' => ['string', 'connection='=>'resource'],
      'new' => ['string', 'connection='=>'pgsql\connection'],
    ],
    'pg_untrace' => [
      'old' => ['bool', 'connection='=>'resource'],
      'new' => ['bool', 'connection='=>'?pgsql\connection'],
    ],
    'pg_update' => [
      'old' => ['bool|string', 'connection'=>'resource', 'table_name'=>'string', 'values'=>'array', 'conditions'=>'array', 'flags='=>'int'],
      'new' => ['bool|string', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'values'=>'array', 'conditions'=>'array', 'flags='=>'int'],
    ],
    'pg_version' => [
      'old' => ['array<string,string>', 'connection='=>'?resource'],
      'new' => ['array<string,string>', 'connection='=>'?pgsql\connection']
    ],
    'pspell_add_to_personal' => [
      'old' => ['bool', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['bool', 'dictionary'=>'PSpell\Dictionary', 'word'=>'string'],
    ],
    'pspell_add_to_session' => [
      'old' => ['bool', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['bool', 'dictionary'=>'PSpell\Dictionary', 'word'=>'string'],
    ],
    'pspell_check' => [
      'old' => ['bool', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['bool', 'dictionary'=>'PSpell\Dictionary', 'word'=>'string'],
    ],
    'pspell_clear_session' => [
      'old' => ['bool', 'dictionary'=>'resource'],
      'new' => ['bool', 'dictionary'=>'PSpell\Dictionary'],
    ],
    'pspell_config_create' => [
      'old' => ['resource', 'language'=>'string', 'spelling='=>'string', 'jargon='=>'string', 'encoding='=>'string'],
      'new' => ['PSpell\Config', 'language'=>'string', 'spelling='=>'string', 'jargon='=>'string', 'encoding='=>'string'],
    ],
    'pspell_config_data_dir' => [
      'old' => ['bool', 'config'=>'resource', 'directory'=>'string'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'directory'=>'string'],
    ],
    'pspell_config_dict_dir' => [
      'old' => ['bool', 'config'=>'resource', 'directory'=>'string'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'directory'=>'string'],
    ],
    'pspell_config_ignore' => [
      'old' => ['bool', 'config'=>'resource', 'min_length'=>'int'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'min_length'=>'int'],
    ],
    'pspell_config_mode' => [
      'old' => ['bool', 'config'=>'resource', 'mode'=>'int'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'mode'=>'int'],
    ],
    'pspell_config_personal' => [
      'old' => ['bool', 'config'=>'resource', 'filename'=>'string'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'filename'=>'string'],
    ],
    'pspell_config_repl' => [
      'old' => ['bool', 'config'=>'resource', 'filename'=>'string'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'filename'=>'string'],
    ],
    'pspell_config_runtogether' => [
      'old' => ['bool', 'config'=>'resource', 'allow'=>'bool'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'allow'=>'bool'],
    ],
    'pspell_config_save_repl' => [
      'old' => ['bool', 'config'=>'resource', 'save'=>'bool'],
      'new' => ['bool', 'config'=>'PSpell\Config', 'save'=>'bool'],
    ],
    'pspell_new' => [
      'old' => ['resource|false', 'language'=>'string', 'spelling='=>'string', 'jargon='=>'string', 'encoding='=>'string', 'mode='=>'int'],
      'new' => ['PSpell\Dictionary|false', 'language'=>'string', 'spelling='=>'string', 'jargon='=>'string', 'encoding='=>'string', 'mode='=>'int'],
    ],
    'pspell_new_config' => [
      'old' => ['resource|false', 'config'=>'resource'],
      'new' => ['PSpell\Dictionary|false', 'config'=>'PSPell\Config'],
    ],
    'pspell_new_personal' => [
      'old' => ['resource|false', 'filename'=>'string', 'language'=>'string', 'spelling='=>'string', 'jargon='=>'string', 'encoding='=>'string', 'mode='=>'int'],
      'new' => ['PSpell\Dictionary|false', 'filename'=>'string', 'language'=>'string', 'spelling='=>'string', 'jargon='=>'string', 'encoding='=>'string', 'mode='=>'int'],
    ],
    'pspell_save_wordlist' => [
      'old' => ['bool', 'dictionary'=>'resource'],
      'new' => ['bool', 'dictionary'=>'PSpell\Dictionary'],
    ],
    'pspell_store_replacement' => [
      'old' => ['bool', 'dictionary'=>'resource', 'misspelled'=>'string', 'correct'=>'string'],
      'new' => ['bool', 'dictionary'=>'PSpell\Dictionary', 'misspelled'=>'string', 'correct'=>'string'],
    ],
    'pspell_suggest' => [
      'old' => ['array', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['array', 'dictionary'=>'PSpell\Dictionary', 'word'=>'string'],
    ],
  ],
  'removed' => [
  ],
];

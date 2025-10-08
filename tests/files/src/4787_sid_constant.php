<?php

/**
 * @phan-file-suppress PhanPluginNoCommentOnFile
 * @phan-file-suppress PhanPluginNoCommentOnFunction
 * @phan-file-suppress PhanPluginRemoveDebugEcho
 */
function useSidConstant(): void
{
    if (SID !== '') {
        echo SID;
    }
}

<?php declare(strict_types=1);

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\AfterAnalyzeFileCapability;
use ast\Node;

/**
 * This plugin invokes the equivalent of `php --no-php-ini --syntax-check $analyzed_file_path`.
 *
 * php-ast reports syntax errors, but does not report all **semantic** errors that `php --syntax-check` would detect.
 *
 * Note that loading PHP modules would slow down analysis, so this plugin adds `--no-php-ini`.
 *
 * NOTE: This may not work in languages other than english.
 * NOTE: .phan/config.php can contain a config to override the PHP binary/binaries used
 *      This can replace the default binary (PHP_BINARY) with an array of absolute path or program names(in $PATH)
 *       E.g. have 'plugin_config' => ['php_native_syntax_check_binaries' => ['php72', 'php70', 'php56']]
 * Note: This may cause Phan to take over twice as long. This is recommended for use with `--processes N`.
 */
class InvokePHPNativeSyntaxCheckPlugin extends PluginV2 implements AfterAnalyzeFileCapability
{
    const LINE_NUMBER_REGEX = "@ on line ([1-9][0-9]*)$@";
    const STDIN_FILENAME_REGEX = "@ in Standard input code@";

    /**
     * TODO: Disable in LSP mode?
     *
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * A context with the file name for $file_contents and the scope after analyzing $node.
     *
     * @param string $file_contents the unmodified file contents @phan-unused-param
     * @param Node $node the node @phan-unused-param
     * @return void
     * @override
     */
    public function afterAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    ) {
        $php_binaries = Config::getValue('plugin_config')['php_native_syntax_check_binaries'] ?? [PHP_BINARY];

        foreach ($php_binaries as $binary) {
            $check_error_message = self::runCheck($binary, $file_contents);
            if ($check_error_message !== null) {
                $lineno = 1;
                if (preg_match(self::LINE_NUMBER_REGEX, $check_error_message, $matches)) {
                    $lineno = (int)$matches[1];
                    $check_error_message = trim(preg_replace(self::LINE_NUMBER_REGEX, '', $check_error_message));
                }
                $check_error_message = preg_replace(self::STDIN_FILENAME_REGEX, '', $check_error_message);


                $this->emitIssue(
                    $code_base,
                    clone($context)->withLineNumberStart($lineno),
                    'PhanNativePHPSyntaxCheckPlugin',
                    'Saw error or notice for {FILE} --syntax-check: {DETAILS}',
                    [
                        $binary === PHP_BINARY ? 'php' : $binary,
                        json_encode($check_error_message),

                    ]
                );
            }
        }
    }

    /**
     * @return ?string - Returns a string on error
     */
    private function runCheck(string $binary, string $file_contents)
    {
        $descriptorspec = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];
        $cmd = 'exec ' . escapeshellarg($binary) . ' --no-php-ini --syntax-check --';
        $process = proc_open($cmd, $descriptorspec, $pipes, null, [], ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return "Failed to run proc_open in " . __METHOD__;
        }
        fwrite($pipes[0], $file_contents);
        fclose($pipes[0]);

        $stdout = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $stderr = trim(stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $exit_code = proc_close($process);
        if ($exit_code === 0) {
            return null;
        }
        $output = str_replace("\r", "", trim($stdout . "\n" . $stderr));
        $first_line = explode("\n", $output)[0];
        return $first_line;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new InvokePHPNativeSyntaxCheckPlugin;

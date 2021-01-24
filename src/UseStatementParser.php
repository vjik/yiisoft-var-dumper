<?php

declare(strict_types=1);

namespace Yiisoft\VarDumper;

use Throwable;

use function defined;

/**
 * UseStatementParser given a PHP file, returns a set of `use` statements from the code.
 */
final class UseStatementParser
{
    /**
     * @param string $file File to read.
     *
     * @return array Use statements data.
     */
    public function fromFile(string $file): array
    {
        try {
            $fileContent = file_get_contents($file);
        } catch (Throwable $e) {
            throw new FailedReadFileException($e->getMessage());
        }
        if ($fileContent === false) {
            throw new FailedReadFileException('Failed read file "' . $file . '".');
        }

        $tokens = token_get_all($fileContent);
        array_shift($tokens);

        $uses = [];
        foreach ($tokens as $i => $token) {
            if (!isset($token[0])) {
                continue;
            }

            if ($token[0] === T_USE) {
                $uses = array_merge($uses, $this->normalizeUse(array_slice($tokens, $i + 1)));
                continue;
            }
        }

        return $uses;
    }

    /**
     * Normalizes raw tokens into uniform use statement data.
     *
     * @param array $tokens Raw tokens.
     *
     * @return array Normalized use statement data.
     */
    private function normalizeUse(array $tokens): array
    {
        $commonNamespace = '\\';
        $current = '';
        $alias = null;
        $uses = [];

        foreach ($tokens as $token) {
            if (!isset($token[0])) {
                continue;
            }
            if (
                $token[0] === T_STRING
                || $token[0] === T_NS_SEPARATOR
                || (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED)
                || (defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED)
                || (defined('T_NAME_RELATIVE') && $token[0] === T_NAME_RELATIVE)
            ) {
                $current .= $token[1];
                continue;
            }
            if ($token === ',' || $token === ';') {
                if ($current !== '') {
                    if ($alias === null) {
                        $uses[] = $commonNamespace . $current;
                    } else {
                        $uses[$alias] = $commonNamespace . $current;
                    }
                    $current = '';
                }
            }
            if ($token === ';') {
                break;
            }
            if ($token === '{') {
                $commonNamespace .= $current;
                $current = '';
                continue;
            }
            if ($token[0] === T_AS) {
                $current .= '@';
            }
        }

        return $this->replaceAliases($uses);
    }

    private function replaceAliases(array $uses): array
    {
        $result = [];
        foreach ($uses as $use) {
            $delimiterPosition = strpos($use, '@');
            if ($delimiterPosition !== false) {
                $alias = mb_substr($use, $delimiterPosition + 1);
                $result[$alias] = mb_substr($use, 0, $delimiterPosition);
            } else {
                $result[substr(strrchr($use, '\\'), 1)] = $use;
            }
        }

        return $result;
    }
}

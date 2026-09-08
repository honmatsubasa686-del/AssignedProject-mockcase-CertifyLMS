<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            '下書き状態かつ受講者・利用履歴が存在しないプランのみ削除できます。',
            $previous,
        );
    }
}

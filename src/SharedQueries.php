<?php
declare(strict_types=1);

namespace ScriptFUSION\Top250\Shared;

use ScriptFUSION\StaticClass;

final class SharedQueries
{
    use StaticClass;

    public const APP_SCORE =
        '(
            (positive_reviews + 1.9208) / total_reviews - 1.96
                * SQRT((positive_reviews * negative_reviews) / total_reviews + 0.9604)
                / total_reviews
        ) / (1 + 3.8416 / total_reviews) AS score'
    ;
}

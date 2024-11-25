<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Mapping;

use ScriptFUSION\Mapper\DataType;
use ScriptFUSION\Mapper\Mapping;
use ScriptFUSION\Mapper\Strategy\Callback;
use ScriptFUSION\Mapper\Strategy\Collection;
use ScriptFUSION\Mapper\Strategy\Copy;
use ScriptFUSION\Mapper\Strategy\IfElse;
use ScriptFUSION\Mapper\Strategy\Join;
use ScriptFUSION\Mapper\Strategy\Regex;
use ScriptFUSION\Mapper\Strategy\Type;
use ScriptFUSION\Steam250\Shared\Platform;

class AppDetailsMapping extends Mapping
{
    private int $appId;

    public function __construct(int $appId)
    {
        $this->appId = $appId;

        parent::__construct();
    }

    protected function createMapping()
    {
        return [
            'name' => new Copy('name'),
            'type' => new Copy('type'),
            'developers' => new Copy('developers'),
            'publishers' => new Copy('publishers'),
            'release_date' => new Callback(
                static function (array $data): ?int {
                    return $data['release_date'] ? $data['release_date']->getTimestamp() : null;
                }
            ),
            'tags' => new Copy('tags'),
            'price' => new Copy('price'),
            'discount_price' => new Copy('discount_price'),
            'discount' => new Copy('discount'),
            'vrx' => new Type(DataType::Integer, new Copy('vrx')),
            'free' => new Type(DataType::Integer, new Copy('free')),
            'videos' => new Join(
                ',',
                new Collection(
                    new Copy('videos'),
                    new Callback(
                        static fn ($_, string $url): string =>
                            preg_replace('[.*/steam/apps/(\d++)/([\da-z]{40}(?=/))?+.*]', '$2$1', $url)
                    ),
                ),
            ),
            'ea' => new Callback(
                static function (array $data): int {
                    return (int)in_array('Early Access', $data['genres'], true);
                }
            ),
            'adult' => new Type(DataType::Integer, new Copy('adult')),
            'positive_reviews' => new Copy('positive_reviews'),
            'negative_reviews' => new Copy('negative_reviews'),
            'total_reviews' => new Callback(
                static function (array $data): int {
                    return $data['positive_reviews'] + $data['negative_reviews'];
                }
            ),
            'steam_reviews' => new copy('steam_reviews'),
            'platforms' => new Callback(
                static function (array $data): int {
                    $platforms = 0;
                    $data['windows'] && $platforms |= Platform::WINDOWS;
                    $data['linux'] && $platforms |= Platform::LINUX;
                    $data['mac'] && $platforms |= Platform::MAC;

                    return $platforms;
                }
            ),
            'steam_deck' => new Callback(
                static fn (array $data): ?int => isset($data['steam_deck']) ? $data['steam_deck']->toId() : null
            ),
            'parent_id' => new IfElse(
                fn ($data) => $data['app_id'] !== $this->appId,
                new Copy('app_id')
            ),
            'alias' => new IfElse(fn ($data) => $data['canonical_id'] !== $this->appId, 1, 0),
            'demo_id' => new Copy('demo_id'),
            'capsule_hash' => new Regex(new Copy('capsule_url'), '[/([\da-f]{40})/]', 1),
            'capsule_alt' => new Regex(new Copy('capsule_url'), '[_alt_assets_(\d+)\.]', 1),
        ];
    }
}

<div class="rewards" aria-label="Récompenses">
<?php foreach (App\Services\CatalogService::REWARDS as $field => $label): ?>
    <?php if ($item[$field] !== null): ?><span class="reward <?= e(substr($field, 7)) ?>"><?= e(preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', (string) $item[$field])) ?> <?= e($label) ?></span><?php endif; ?>
<?php endforeach; ?>
<?php foreach ($item['titles'] as $rewardTitle): ?><span class="reward titles">♛ <?= e($rewardTitle) ?></span><?php endforeach; ?>
<?php if ($item['title_rewards_known'] && !$item['titles']): ?><span class="reward no-title">Aucun titre</span><?php endif; ?>
</div>
<?php if (!App\Services\CatalogService::rewardsKnown($item)): ?><p class="unknown-rewards">Récompenses à confirmer <span>· informations incomplètes</span></p><?php endif; ?>

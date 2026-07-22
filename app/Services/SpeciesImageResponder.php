<?php

namespace App\Services;

use App\Models\AgentConversationMessage;
use App\Models\Champion;
use App\Models\Species;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Answers "show me a photo of X" from the catalogue.
 *
 * Photo requests are served directly from stored image URLs rather than by the
 * model, so the answer always points at real records. Shared by the web and
 * mobile clients so both behave the same way.
 */
class SpeciesImageResponder
{
    private const CONTEXT_MESSAGE_LIMIT = 12;

    public function isImageRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        // "pic"/"pics" are as common as the full words in practice.
        return preg_match(
            '/\b(pic|pics|photo|photos|photograph|photographs|image|images|picture|pictures)\b/i',
            $normalized
        ) === 1;
    }

    /**
     * Markdown for the request, preferring a matching champion over a species.
     */
    public function respond(string $message, ?string $conversationId, string $recentContext = ''): string
    {
        $combined = $this->withContext($message, $recentContext);

        if ($champion = $this->findChampion($combined)) {
            return $this->championImages($champion);
        }

        return $this->speciesImages($combined, $conversationId);
    }

    /**
     * Short follow-ups ("show me its photo") only make sense alongside what was
     * said before, so they are widened with recent context.
     */
    public function withContext(string $message, string $recentContext): string
    {
        $trimmed = trim($message);

        if ($recentContext === '') {
            return $trimmed;
        }

        $needsContext = mb_strlen($trimmed) <= 40
            || preg_match('/\b(it|that|this|same|again|those|these)\b/i', $trimmed) === 1;

        return $needsContext ? trim($trimmed.' '.$recentContext) : $trimmed;
    }

    public function recentContext(?string $conversationId, int $limit = 8): string
    {
        return $this->recentContents($conversationId, $limit)
            ->map(fn (string $value) => $this->stripChartPayloads($value))
            ->filter(fn (string $value) => $value !== '')
            ->implode("\n");
    }

    private function speciesImages(string $combined, ?string $conversationId): string
    {
        $species = $this->findSpecies($combined)
            ?? $this->findSpeciesInHistory($conversationId);

        if ($species === null) {
            return 'I could not find a matching species with photos. '
                .'Please provide a species name or source id.';
        }

        $imageUrls = collect(is_array($species->image_urls) ? $species->image_urls : [])
            ->map(fn ($url) => is_string($url) ? trim($url) : null)
            ->filter(fn ($url) => is_string($url) && $url !== '' && preg_match('/^https?:\/\//i', $url) === 1)
            ->take(6)
            ->values();

        if ($imageUrls->isEmpty()) {
            return "**{$species->scientific_name}** (source id: {$species->source_id}) — "
                .'no image URLs are stored in database for this record.';
        }

        $images = $imageUrls
            ->map(fn (string $url, int $index) => "![{$species->scientific_name} image ".($index + 1)."]({$url})")
            ->implode("\n");

        return "**{$species->scientific_name}** (source id: {$species->source_id})\n\n"
            .$images
            ."\n\nSpecies page: https://species.phakhaolao.la/search/specie_details/{$species->source_id}";
    }

    private function findSpecies(string $combined): ?Species
    {
        if (preg_match('/specie_details\/(\d+)/i', $combined, $matches) === 1) {
            if ($species = Species::query()->where('source_id', (int) $matches[1])->first()) {
                return $species;
            }
        }

        if (preg_match('/\bsource[\s_-]*id\s*(\d+)\b/i', $combined, $matches) === 1) {
            if ($species = Species::query()->where('source_id', (int) $matches[1])->first()) {
                return $species;
            }
        }

        $query = $this->searchTerm($combined);

        if ($query === '') {
            return null;
        }

        $like = '%'.$query.'%';

        return Species::query()
            ->where(function (Builder $q) use ($like): void {
                $q->where('scientific_name', 'like', $like)
                    ->orWhere('common_name_english', 'like', $like)
                    ->orWhere('common_name_lao', 'like', $like);
            })
            ->orderBy('source_id')
            ->first();
    }

    /**
     * Reduce a question to the name being asked about.
     *
     * Punctuation is dropped as well as filler words: leaving it behind turns
     * "monkey ,  ?" into a LIKE that matches nothing.
     */
    private function searchTerm(string $message): string
    {
        $term = (string) preg_replace(
            '/\b(show|display|give|find|some|any|me|the|a|an|pic|pics|photo|photos|photograph|photographs'
            .'|image|images|picture|pictures|of|for|about|please|can|could|you|how|many|species|there|are|is)\b/i',
            ' ',
            $message
        );

        // Keep letters, digits, spaces and hyphens; Lao script must survive.
        $term = (string) preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $term);

        return trim((string) preg_replace('/\s+/', ' ', $term));
    }

    private function findSpeciesInHistory(?string $conversationId): ?Species
    {
        foreach ($this->recentContents($conversationId, self::CONTEXT_MESSAGE_LIMIT) as $text) {
            if (preg_match('/specie_details\/(\d+)/i', $text, $matches) === 1) {
                if ($species = Species::query()->where('source_id', (int) $matches[1])->first()) {
                    return $species;
                }
            }

            if (preg_match('/\bsource[\s_-]*id[:\s]*(\d+)\b/i', $text, $matches) === 1) {
                if ($species = Species::query()->where('source_id', (int) $matches[1])->first()) {
                    return $species;
                }
            }

            // A capitalised binomial such as "Macaca mulatta".
            if (preg_match('/\b([A-Z][a-z]+)\s+([a-z][a-z-]+)\b/', $text, $matches) === 1) {
                $binomial = strtolower(trim($matches[1].' '.$matches[2]));

                $species = Species::query()
                    ->whereRaw('LOWER(scientific_name) LIKE ?', [$binomial.'%'])
                    ->orderBy('source_id')
                    ->first();

                if ($species) {
                    return $species;
                }
            }
        }

        return null;
    }

    private function findChampion(string $combined): ?Champion
    {
        $name = $this->searchTerm(preg_replace('/\bchampions?\b/i', ' ', $combined) ?? $combined);

        if (mb_strlen($name) < 3) {
            return null;
        }

        return Champion::query()
            ->where('name', 'like', '%'.$name.'%')
            ->orderBy('id')
            ->first();
    }

    private function championImages(Champion $champion): string
    {
        $images = collect([$champion->featured_image])
            ->merge(is_array($champion->gallery) ? $champion->gallery : [])
            ->map(fn ($url) => is_string($url) ? trim($url) : null)
            ->filter(fn ($url) => is_string($url) && preg_match('/^https?:\/\//i', $url) === 1)
            ->unique()
            ->take(6)
            ->values();

        if ($images->isEmpty()) {
            return "**{$champion->name}** — no images are stored for this champion.";
        }

        $markdown = $images
            ->map(fn (string $url, int $index) => "![{$champion->name} image ".($index + 1)."]({$url})")
            ->implode("\n");

        return "**{$champion->name}**\n\n".$markdown
            .($champion->source_url ? "\n\nRead more: {$champion->source_url}" : '');
    }

    /**
     * @return Collection<int, string>
     */
    private function recentContents(?string $conversationId, int $limit): Collection
    {
        if ($conversationId === null) {
            return collect();
        }

        return AgentConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('content')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values();
    }

    private function stripChartPayloads(string $text): string
    {
        return trim((string) preg_replace('/\[CHART\].*?\[\/CHART\]/s', '', $text));
    }
}

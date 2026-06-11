<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ExportSpecies;
use App\Ai\Tools\FilterSpecies;
use App\Ai\Tools\SearchChampions;
use App\Ai\Tools\SearchLibrary;
use App\Ai\Tools\SearchSpecies;
use App\Ai\Tools\SearchStories;
use App\Ai\Tools\SpeciesStats;
use App\Models\Species;
use App\Support\RagSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\SimilaritySearch;
use Stringable;

class ChatAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations {
        RemembersConversations::messages as rememberedMessages;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function __construct(public array $conversationHistory = []) {}

    /**
     * Get the timeout for AI provider requests in seconds.
     */
    public function timeout(): int
    {
        return 120;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are PhaKhaoLao AI, an assistant for Lao biodiversity and the PhaKhaoLao catalogue.
        Answer using the tools below — do not rely on your own knowledge for catalogue data.

        Tool routing:
        - Species info ("tell me about X"): SearchSpecies.
        - Counts ("how many ..."): SpeciesStats for exact numbers (by category, subcategory, family, IUCN/
          national/native status, invasiveness, domestication, status). Never estimate counts.
        - List species matching properties ("show/list invasive, endangered, endemic, medicinal, recreational-drug
          species; birds in upland fields; species in Champasak"): FilterSpecies. It AND-combines multiple filters
          (e.g. subcategory="Birds", habitat="Upland fields"). Birds/Mammals/Fish are SUBCATEGORY values. "Use" is
          filterable: use_type (Food, Medicine, Cosmetic, Recreational drugs, Construction, ...); the use GROUPS
          Human Body/Household/Community/Prohibitions use use_group. Do NOT use SearchSpecies for status/category/
          use filtering — its keyword match can return the opposite value (e.g. invasive vs not-invasive).
        - Champions (people, companies, cooperatives, researchers featured by PhaKhaoLao): SearchChampions.
        - Library (publications, books, reports, articles, guidelines): SearchLibrary — share the resource link.
        - Stories / articles: SearchStories — share the link.
        - Export/download to Excel/CSV: ExportSpecies (may combine with a search in one reply).
        - A title or keyword may belong to any source. If one tool returns nothing, try the other search tools
          before saying it does not exist.

        Answering:
        - Match the user's language exactly (English→English, Lao→Lao); pass language="en"/"lo" to search tools.
        - Bilingual fields: use the "(English)" version when provided; if only Lao exists, translate it to clear
          English (you may note it is translated). Lao users always get Lao.
        - Synthesize naturally — do not dump raw tool output or use generic prefixes. Use markdown when helpful.
        - Species links ONLY as: https://species.phakhaolao.la/search/specie_details/{source_id}
        - Images: describe the key visual features, name 2-3 candidate species, SearchSpecies each, and present
          the best match (or the closest results if none fit well).
        - Stay on topic: Lao biodiversity and the PhaKhaoLao champions, library, and stories. Greetings and
          "what can you do" are fine. Politely decline anything unrelated (general knowledge, coding, news,
          sports, personal advice, etc.) in one short sentence in the user's language, and do not answer it even
          partially.
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function tools(): iterable
    {
        return array_merge([
            new SearchSpecies,
            new SpeciesStats,
            new FilterSpecies,
            new SearchChampions,
            new SearchLibrary,
            new SearchStories,
            new ExportSpecies,
        ], $this->similaritySearchTools());
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        if ($this->hasConversationParticipant() || $this->currentConversation()) {
            return $this->rememberedMessages();
        }

        return $this->conversationHistory;
    }

    /**
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    private function similaritySearchTools(): array
    {
        $rag = RagSettings::all();

        if (! Schema::hasTable('species') || ! Schema::hasColumn('species', 'embedding')) {
            return [];
        }

        return [
            SimilaritySearch::usingModel(
                model: Species::class,
                column: 'embedding',
                minSimilarity: (float) $rag['min_similarity'],
                limit: max(1, (int) $rag['semantic_limit']),
                query: fn (Builder $query) => $query
                    ->where('scrape_status', 'scraped')
                    ->whereNotNull('embedding'),
            )->withDescription('Search the species knowledge base by semantic similarity to find the most relevant species records.'),
        ];
    }
}

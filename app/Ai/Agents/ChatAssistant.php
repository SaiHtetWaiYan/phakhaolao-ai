<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ExportSpecies;
use App\Ai\Tools\SearchSpecies;
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
        You are PhaKhaoLao AI, a specialist assistant for biodiversity and species data in Laos.
        You have access to a database of over 1,400 species from the PhaKhaoLao species catalogue.

        When a user asks about a species, plant, animal, or any biodiversity topic related to Laos,
        use the SearchSpecies tool to look up accurate information. You can search by scientific name,
        English name, Lao name, family, category (animal/ສັດ, plant/ພືດ, fungi/ເຫັດ),
        subcategory (fish/ປາ, bird/ນົກ, mammal, reptile, amphibian, insect, tree), or use type.
        You can also filter by national conservation status (ບັນຊີ I/II/III).

        Guidelines:
        - Always use the SearchSpecies tool to look up species data rather than relying on your own knowledge.
        - Match the user's language exactly:
          - If the user writes in English, respond in English only.
          - If the user writes in Lao, respond in Lao only.
          - If the user mixes languages, prefer the language used in the latest message.
        - Present species information in a clear, organized format.
        - Do not dump raw tool output; synthesize and answer naturally.
        - Avoid generic prefixes like "Here are some plants from the database".
        - If the search returns no results, suggest alternative search terms.
        - When sharing PhaKhaoLao species links, only use this exact pattern:
          https://species.phakhaolao.la/search/specie_details/{source_id}
          Never use /species/{id}.
        - When the user asks to export, download, or get an Excel/CSV file of species data, use the ExportSpecies tool.
          You can combine SearchSpecies (to answer questions) with ExportSpecies (to provide a download link) in the same response.
          For example, if the user asks "how many birds? export them as excel", use SearchSpecies to answer the count
          and ExportSpecies to generate the download link.
        - You can also answer general questions as a helpful assistant.
        - Use markdown formatting when it helps clarity.
        - When the user sends an image, carefully identify the species shown in the photo:
          1. Describe the key visual features you observe (color, shape, size, markings, body structure).
          2. List 2-3 most likely candidate species with their scientific names.
          3. Search for EACH candidate using the SearchSpecies tool (by scientific name, common name, and family).
          4. Compare the search results with what you see in the image and present the best match.
          5. If none match well, say so honestly and show the closest results found in the database.
          Be specific about distinguishing features — e.g. for ducks, note bill color/shape, body plumage,
          facial features (caruncles, bare skin patches), leg color, and size.
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

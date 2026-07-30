@extends('layouts.app')

@section('title', 'PhaKhaoLao AI')

@section('content')
<div id="chat-app" class="flex h-full bg-white dark:bg-zinc-950">
    {{-- Sidebar Overlay --}}
    <div id="sidebar-overlay" class="fixed inset-0 z-40 bg-zinc-900/50 backdrop-blur-sm hidden md:hidden"></div>

    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-[280px] flex flex-col bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 transition-transform duration-300 -translate-x-full md:translate-x-0 md:relative md:flex md:w-[260px]">
        {{-- Sidebar Header --}}
        <div class="h-14 flex items-center justify-between px-4 border-b border-zinc-200 dark:border-zinc-800">
            <a href="{{ route('chat') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo.webp') }}" alt="Logo" class="h-8 w-auto dark:filter-[invert(1)_hue-rotate(180deg)]">
            </a>
            <button id="close-sidebar-btn" class="md:hidden p-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- New Chat Button --}}
        <div class="p-4 pb-2">
            <a
                href="{{ route('chat') }}"
                class="w-full flex items-center gap-2 px-4 py-3 bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm text-zinc-700 dark:text-zinc-200 transition-colors shadow-sm"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span data-i18n="new_chat">New Chat</span>
            </a>
        </div>

        {{-- History List --}}
        <div class="px-4 pt-2">
            <input
                type="search"
                id="chat-search"
                data-i18n-ph="search_chats"
                placeholder="Search chats"
                class="w-full px-3 py-2 text-sm rounded-md bg-zinc-100 dark:bg-zinc-800 border border-transparent focus:border-accent-500 focus:outline-none text-zinc-700 dark:text-zinc-200 placeholder:text-zinc-400"
            >
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-2 space-y-1 custom-scrollbar" id="chat-history">
            @foreach($conversations as $conv)
                <div class="js-conv group relative flex items-center" data-updated="{{ optional($conv->updated_at)->toIso8601String() }}" data-title="{{ Str::lower($conv->title) }}" data-title-full="{{ $conv->title }}">
                    <a href="{{ route('chat', $conv->id) }}" 
                       class="flex-1 flex items-center pl-3 pr-10 py-2 text-sm text-left {{ (isset($currentConversation) && $currentConversation->id === $conv->id) ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} rounded-md transition-colors min-w-0">
                        {{-- Full title: the span already ellipsises what will
                             not fit, so cutting at 18 characters only threw
                             away words the sidebar had room for. --}}
                        <span class="truncate block min-w-0" title="{{ $conv->title }}">{{ $conv->title }}</span>
                    </a>
                    <button onclick="promptDeleteConversation('{{ $conv->id }}', event)" 
                            class="absolute right-1.5 opacity-0 group-hover:opacity-100 p-1.5 text-zinc-400 hover:text-red-500 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            @endforeach
        </div>

        {{-- User Profile / Settings --}}
        <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 space-y-2">
            <button data-theme-toggle aria-label="Switch theme" class="w-full flex items-center gap-3 px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors text-zinc-600 dark:text-zinc-400">
                <div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center">
                    <svg data-theme-icon-dark class="hidden w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg data-theme-icon-light class="hidden w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                </div>
                <span class="text-sm font-medium" data-i18n="switch_theme">Switch Theme</span>
            </button>
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-8 h-8 rounded-full bg-accent-100 dark:bg-accent-900 flex items-center justify-center text-accent-600 dark:text-accent-400 font-medium text-xs">
                    US
                </div>
                <div class="flex-1 text-left">
                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">User</div>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main Chat Area --}}
    <main id="chat-container" class="flex-1 flex flex-col h-full relative overflow-hidden">
        {{-- Drag & Drop Overlay --}}
        <div id="drop-overlay" class="absolute inset-0 z-50 bg-accent-600/10 backdrop-blur-[2px] border-2 border-dashed border-accent-500 rounded-3xl m-4 hidden flex-col items-center justify-center transition-all duration-300 pointer-events-none">
            <div class="bg-white dark:bg-zinc-900 px-8 py-6 rounded-3xl shadow-2xl flex flex-col items-center gap-4 transform scale-110">
                <div class="w-16 h-16 bg-accent-100 dark:bg-accent-900/50 rounded-2xl flex items-center justify-center text-accent-600 dark:text-accent-400">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-zinc-900 dark:text-white">Drop to upload</p>
                    <p class="text-sm text-zinc-500">Image will be added to your message</p>
                </div>
            </div>
        </div>

        {{-- Mobile Header --}}
        <header class="md:hidden shrink-0 h-14 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between px-4 bg-white dark:bg-zinc-950 z-10">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/logo.webp') }}" alt="Logo" class="h-7 w-auto dark:filter-[invert(1)_hue-rotate(180deg)]">
            </div>
            <div class="flex items-center gap-1">
                <div class="js-lang-switch flex items-center rounded-lg bg-zinc-100 dark:bg-zinc-800 p-0.5 text-[11px] font-semibold mr-1" title="Answer language">
                    <button type="button" data-lang="auto" class="js-lang-opt px-2 py-0.5 rounded-md transition-colors">Auto</button>
                    <button type="button" data-lang="en" class="js-lang-opt px-2 py-0.5 rounded-md transition-colors">EN</button>
                    <button type="button" data-lang="lo" class="js-lang-opt px-2 py-0.5 rounded-md transition-colors">ລາວ</button>
                </div>
                <button data-theme-toggle class="p-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors" title="Switch Theme">
                    <svg data-theme-icon-dark class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg data-theme-icon-light class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                </button>
                <button id="mobile-menu-btn" class="p-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </header>

        {{-- Top Bar (Desktop) --}}
        <header class="hidden md:flex shrink-0 h-14 border-b border-zinc-100 dark:border-zinc-800/50 items-center justify-between px-6 bg-white/80 dark:bg-zinc-950/80 backdrop-blur-sm z-10 absolute top-0 left-0 right-0">
            {{-- Nothing on the left: the model picker here offered a chevron
                 and a menu that never existed, and there is only one model. --}}
            <div></div>
            <div class="flex items-center gap-3">
                <div class="js-lang-switch flex items-center rounded-lg bg-zinc-100 dark:bg-zinc-800 p-0.5 text-xs font-semibold" title="Answer language">
                    <button type="button" data-lang="auto" class="js-lang-opt px-2.5 py-1 rounded-md transition-colors">Auto</button>
                    <button type="button" data-lang="en" class="js-lang-opt px-2.5 py-1 rounded-md transition-colors">EN</button>
                    <button type="button" data-lang="lo" class="js-lang-opt px-2.5 py-1 rounded-md transition-colors">ລາວ</button>
                </div>
                @if(isset($currentConversation))
                <button
                    onclick="deleteCurrentConversation()"
                    title="Delete Conversation"
                    class="p-2 text-zinc-400 hover:text-red-500 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"></path>
                    </svg>
                </button>
                @endif
            </div>
        </header>

        {{-- Messages Container --}}
        <div id="messages" class="flex-1 overflow-y-auto scroll-smooth pt-14 pb-4">
            <div class="max-w-3xl mx-auto flex flex-col gap-6 px-4 py-8" id="messages-container">
                {{-- Welcome State --}}
                <div id="welcome-message" class="flex flex-col items-center justify-center py-4 md:py-6 text-center animate-fade-in-up" style="{{ !empty($messages) ? 'display: none;' : '' }}">
                    <div class="mb-5">
                        <img src="{{ asset('images/logo.webp') }}" alt="PhaKhaoLao AI" class="h-14 md:h-16 w-auto dark:filter-[invert(1)_hue-rotate(180deg)]">
                    </div>
                    <h2 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2" data-i18n="welcome_title">Hi, I am PhaKhaoLao AI</h2>
                    <p class="text-zinc-500 dark:text-zinc-400 max-w-md" data-i18n="welcome_subtitle">I answer your questions about Lao agrobiodiversity by looking up relevant information on the Pha Khao Lao knowledge base (species database, library, champions database, multimedia content).</p>
                    {{-- Three suggestions, drawn once per load, so the blank page
                         shows what this can actually be asked. --}}
                    <div id="starters" class="mt-6 w-full max-w-xl flex flex-col gap-2"></div>
                </div>

                {{-- Render Existing Messages --}}
                @if(!empty($messages))
                    @foreach($messages as $msg)
                        @if($msg['role'] === 'user')
                            <div class="chat-message js-user-message relative flex flex-col items-end w-full animate-fade-in" data-at="{{ $msg['at'] ?? '' }}">
                                <div class="max-w-[85%] md:max-w-[75%] px-4 py-3 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white text-[15px] leading-relaxed shadow-sm">
                                    @if(!empty($msg['meta']['image_url']))
                                        <div class="mb-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                            <img src="{{ $msg['meta']['image_url'] }}" alt="Uploaded image" class="max-h-80 w-auto object-contain" />
                                        </div>
                                    @endif
                                    @if($msg['content'])
                                        <div class="js-user-text px-1 py-0.5 whitespace-pre-wrap [overflow-wrap:anywhere] break-words">{{ $msg['content'] }}</div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="chat-message relative flex justify-start w-full animate-fade-in group" data-at="{{ $msg['at'] ?? '' }}">
                                <div class="w-full">
                                    <textarea class="hidden js-assistant-raw">{{ $msg['content'] ?? '' }}</textarea>
                                    <div class="prose prose-zinc dark:prose-invert max-w-none text-[15px] leading-relaxed js-assistant-rendered"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
                {{-- The mark appears once, at the end, rather than beside every
                     reply, and carries the reminder with it. --}}
                <div id="conversation-footer" class="flex items-center gap-3 pt-2 {{ empty($messages) ? 'hidden' : '' }}">
                    <img src="{{ asset('favicon-192.png') }}" alt="" class="w-7 h-7 shrink-0">
                    <p class="flex-1 text-right text-xs text-zinc-400 dark:text-zinc-500 whitespace-pre-line" data-i18n="disclaimer">PhaKhaoLao AI can make mistakes.
Please double-check responses.</p>
                </div>
            </div>
            <div id="composer-spacer" class="h-24 {{ empty($messages) ? 'hidden' : '' }}"></div> {{-- Clears the floating composer --}} 
        </div>

        {{-- Input Area --}}
        <footer class="shrink-0 px-4 pb-4 md:pb-6 relative">
            <div class="max-w-3xl mx-auto">
                {{-- Error Message --}}
                <div id="error-message" class="mb-2 px-4 py-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm rounded-lg hidden flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span id="error-text"></span>
                </div>

                {{-- Image Preview --}}
                <div id="image-preview" class="mb-3 hidden transition-all duration-300 ease-in-out transform scale-95 opacity-0">
                    <div class="relative inline-flex p-1.5 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-md">
                        <img id="image-preview-img" src="" alt="Upload preview" class="h-20 w-20 rounded-lg object-cover">
                        <button
                            type="button"
                            id="image-preview-remove"
                            class="absolute -top-2 -right-2 w-6 h-6 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-full flex items-center justify-center hover:bg-red-500 dark:hover:bg-red-500 transition-colors shadow-lg group"
                        >
                            <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <form id="chat-form" class="relative group">
                    <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
                    <button
                        type="button"
                        id="upload-btn"
                        title="Upload image"
                        class="absolute left-2 top-1/2 -translate-y-1/2 p-2.5 text-zinc-400 hover:text-accent-600 dark:hover:text-accent-400 transition-colors rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </button>
                    <input
                        type="text"
                        id="message-input"
                        name="message"
                        placeholder="Message PhaKhaoLao AI..."
                        data-i18n-ph="placeholder"
                        autocomplete="off"
                        class="w-full pl-12 pr-24 py-4 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 rounded-2xl shadow-sm transition-all text-[15px] placeholder:text-zinc-400"
                    >
                    <button
                        type="button"
                        id="mic-btn"
                        title="Voice input"
                        class="absolute right-12 top-1/2 -translate-y-1/2 p-2.5 text-zinc-400 hover:text-accent-600 dark:hover:text-accent-400 transition-colors rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-14 0m7 7v3m-4 0h8M12 1a3 3 0 00-3 3v6a3 3 0 006 0V4a3 3 0 00-3-3z"></path>
                        </svg>
                    </button>
                    <button
                        type="submit"
                        id="send-btn"
                        aria-label="Send message"
                        class="absolute right-2 top-1/2 -translate-y-1/2 p-2.5 bg-accent-500 text-accent-900 rounded-xl hover:bg-accent-600 disabled:opacity-30 disabled:hover:bg-accent-500 transition-all shadow-sm group"
                        disabled
                    >
                        <svg class="js-send-icon w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                        <svg class="js-stop-icon hidden w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="2.5"></rect>
                        </svg>
                    </button>
                </form>
                <div class="text-center mt-2">

                </div>
            </div>
        </footer>
    </main>
</div>

<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-zinc-900">
        <p id="delete-modal-question" class="text-base leading-relaxed text-zinc-900 dark:text-zinc-100">Are you sure you want to delete this chat?</p>
        <div class="mt-5 flex items-center justify-end gap-2">
            <button id="delete-modal-cancel" type="button" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800" data-i18n="cancel">Cancel</button>
            <button id="delete-modal-confirm" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-500" data-i18n="delete">Delete</button>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script>
let currentConversationId = @json($currentConversation->id ?? null);
const CURRENT_CONVERSATION_TITLE = @json($currentConversation->title ?? '');
let pendingDeleteConversationId = null;

function promptDeleteConversation(id, event) {
    if (event) event.preventDefault();
    pendingDeleteConversationId = id;

    askDeleteQuestion(id);

    const modal = document.getElementById('delete-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

/**
 * Name the chat in the question, so there is no doubt which one is going.
 * The visible row title is truncated, hence the full one on the row.
 */
function askDeleteQuestion(id) {
    const question = document.getElementById('delete-modal-question');
    if (!question) return;

    const row = document.querySelector(`.js-conv [href$="/${id}"]`)?.closest('.js-conv');
    const title = row?.dataset.titleFull || CURRENT_CONVERSATION_TITLE;

    const template = window.pklT?.('delete_confirm');
    if (template) question.textContent = template.replace('{title}', title || '');
}

function closeDeleteModal() {
    const modal = document.getElementById('delete-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pendingDeleteConversationId = null;
}

async function deleteConversation(id) {
    try {
        const response = await fetch(`/chat/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });

        if (response.ok) {
            // Deleting reloads the page, which would wipe a toast raised here,
            // so leave the news for the page that comes back.
            sessionStorage.setItem(DELETED_FLAG, '1');

            if (id === currentConversationId) {
                window.location.href = '{{ route("chat") }}';
            } else {
                window.location.reload();
            }

            return;
        }

        pkToast(window.pklT?.('delete_failed') || 'Could not delete that chat.', TRASH_SVG);
    } catch (e) {
        console.error('Delete failed', e);
        pkToast(window.pklT?.('delete_failed') || 'Could not delete that chat.', TRASH_SVG);
    }
}

const DELETED_FLAG = 'pkl_conversation_deleted';
const TRASH_SVG = '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"></path></svg>';

/**
 * Pick up the name the assistant gave this conversation.
 *
 * The title is written by a job that runs after the reply, so the sidebar
 * was rendered with the opening question and would keep showing it until
 * the next page load. Ask twice: once for a quick model, once for a slow one.
 */
function refreshConversationTitle(id, attempt = 0) {
    if (!id || attempt > 1) return;

    setTimeout(async () => {
        try {
            const response = await fetch(`/chat/${id}/title`, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;

            const { title } = await response.json();
            if (!title) return;

            const row = document.querySelector(`.js-conv [href$="/${id}"]`)?.closest('.js-conv');
            const label = row?.querySelector('a span');

            if (label && label.textContent.trim() !== title) {
                label.textContent = title;
                label.title = title;
                row.dataset.titleFull = title;
                row.dataset.title = title.toLowerCase();
                return;
            }

            refreshConversationTitle(id, attempt + 1);
        } catch (e) {
            // A title that stays as the question is a cosmetic loss.
        }
    }, attempt === 0 ? 2500 : 5000);
}

/** A pill that drops in below the header to report what just happened. */
function pkToast(message, icon) {
    document.getElementById('pk-toast')?.remove();

    const toast = document.createElement('div');
    toast.id = 'pk-toast';
    toast.className = 'pk-toast fixed right-4 top-20 z-50 flex items-center gap-3 px-5 py-3 rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-sm font-medium text-zinc-800 dark:text-zinc-100 shadow-lg';
    toast.innerHTML = `${icon || ''}<span></span>`;
    toast.querySelector('span').textContent = message;

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function deleteCurrentConversation() {
    if (currentConversationId) {
        promptDeleteConversation(currentConversationId);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chat-form');
    const input = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const messagesContainer = document.getElementById('messages-container');
    const messagesArea = document.getElementById('messages');
    const welcomeMessage = document.getElementById('welcome-message');
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const deleteModal = document.getElementById('delete-modal');
    const deleteModalCancel = document.getElementById('delete-modal-cancel');
    const deleteModalConfirm = document.getElementById('delete-modal-confirm');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const imageInput = document.getElementById('image-input');
    const uploadBtn = document.getElementById('upload-btn');
    const imagePreview = document.getElementById('image-preview');
    const imagePreviewImg = document.getElementById('image-preview-img');
    const imagePreviewRemove = document.getElementById('image-preview-remove');
    const themeToggleButtons = Array.from(document.querySelectorAll('[data-theme-toggle]'));

    // Mobile Sidebar Elements
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const closeSidebarBtn = document.getElementById('close-sidebar-btn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        sidebarOverlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    mobileMenuBtn?.addEventListener('click', openSidebar);
    closeSidebarBtn?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    function syncThemeToggleIcons() {
        const isDark = document.documentElement.classList.contains('dark');
        themeToggleButtons.forEach((button) => {
            const darkIcon = button.querySelector('[data-theme-icon-dark]');
            const lightIcon = button.querySelector('[data-theme-icon-light]');
            if (!darkIcon || !lightIcon) return;

            if (isDark) {
                lightIcon.classList.remove('hidden');
                darkIcon.classList.add('hidden');
            } else {
                darkIcon.classList.remove('hidden');
                lightIcon.classList.add('hidden');
            }
        });
    }

    function toggleTheme() {
        const root = document.documentElement;

        // Every surface carries its own colour transition, so a switch
        // arrived in pieces — each element easing at its own pace while the
        // ones without a transition snapped. Hold them all still for the
        // swap, then hand the transitions back.
        root.classList.add('pk-theme-switching');

        const isDark = root.classList.contains('dark');
        if (isDark) {
            root.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        } else {
            root.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        }
        syncThemeToggleIcons();

        // Two frames: one for the new colours to paint, one to release. The
        // timer is the backstop — a hidden tab never paints, so the frames
        // would never come and transitions would stay dead for good.
        const release = () => root.classList.remove('pk-theme-switching');

        requestAnimationFrame(() => requestAnimationFrame(release));
        setTimeout(release, 120);
    }

    syncThemeToggleIcons();
    themeToggleButtons.forEach((button) => {
        button.addEventListener('click', toggleTheme);
    });

    const chatContainer = document.getElementById('chat-container');
    const dropOverlay = document.getElementById('drop-overlay');

    let isStreaming = false;
    let abortController = null;

    // Answer-language switch (auto / en / lo), persisted in the browser.
    const LANG_KEY = 'pkl_response_language';
    function getResponseLanguage() {
        return localStorage.getItem(LANG_KEY) || 'auto';
    }

    // Interface translations. "auto" and "en" use English; "lo" uses Lao.
    const I18N = {
        en: {
            new_chat: 'New Chat',
            recent: 'Recent',
            switch_theme: 'Switch Theme',
            welcome_title: 'Hi, I am PhaKhaoLao AI',
            welcome_subtitle: 'I answer your questions about Lao agrobiodiversity by looking up relevant information on the Pha Khao Lao knowledge base (species database, library, champions database, multimedia content).',
            placeholder: 'Message PhaKhaoLao AI...',
            disclaimer: 'PhaKhaoLao AI can make mistakes.\nPlease double-check responses.',
            delete_confirm: 'Are you sure you want to delete "{title}"?',
            cancel: 'Cancel',
            delete: 'Delete',
            deleted: 'Conversation deleted',
            nothing_to_read: 'There is nothing to read aloud here.',
            delete_failed: 'Could not delete that chat.',
            search_chats: 'Search chats',
            date_today: 'TODAY',
            date_last_7: 'LAST 7 DAYS',
            date_earlier: 'EARLIER',
            just_now: 'Just now',
            minute_ago: '1 minute ago',
            minutes_ago: '{n} minutes ago',
            hour_ago: '1 hour ago',
            hours_ago: '{n} hours ago',
            day_ago: 'Yesterday',
            days_ago: '{n} days ago',
            copy: 'Copy',
            listen: 'Listen',
            cat_species: 'SPECIES',
            cat_champions: 'CHAMPIONS',
            cat_library: 'LIBRARY',
            cat_stories: 'STORIES',
            cat_conservation: 'CONSERVATION',
            starter_plants: 'Which plants are used in Lao cooking?',
            starter_animals: 'What animals live in Laos?',
            starter_medicinal: 'Which plants are used as medicine?',
            starter_birds: 'Which birds are found in Laos?',
            starter_fish: 'Which fish live in the Mekong?',
            starter_matrix: 'Compare three ferns in a table',
            starter_champions: 'How many champions per province?',
            starter_library: 'What does the library say about agroforestry?',
            starter_stories: 'Show me stories about farming',
            starter_invasive: 'Which species are invasive in Laos?',
        },
        lo: {
            new_chat: 'ສ້າງການສົນທະນາໃໝ່',
            recent: 'ຫຼ້າສຸດ',
            switch_theme: 'ສະຫຼັບຮູບແບບ',
            welcome_title: 'ສະບາຍດີ, ຂ້ອຍແມ່ນ PhaKhaoLao AI',
            welcome_subtitle: 'ຂ້ອຍຕອບຄຳຖາມກ່ຽວກັບຊີວະນາໆພັນກະສິກຳລາວ ໂດຍຄົ້ນຫາຂໍ້ມູນທີ່ກ່ຽວຂ້ອງ ຈາກຖານຄວາມຮູ້ Pha Khao Lao (ຖານຂໍ້ມູນຊະນິດພັນ, ຫ້ອງສະໝຸດ, ຖານຂໍ້ມູນຜູ້ນຳ, ສື່ມັນຕິມີເດຍ).',
            placeholder: 'ພິມຂໍ້ຄວາມຫາ PhaKhaoLao AI...',
            disclaimer: 'PhaKhaoLao AI ອາດຜິດພາດໄດ້\nກະລຸນາກວດສອບຄຳຕອບຄືນ.',
            delete_confirm: 'ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບ "{title}"?',
            cancel: 'ຍົກເລີກ',
            delete: 'ລຶບ',
            deleted: 'ລຶບການສົນທະນາແລ້ວ',
            nothing_to_read: 'ບໍ່ມີເນື້ອຫາໃຫ້ອ່ານອອກສຽງ.',
            delete_failed: 'ບໍ່ສາມາດລຶບການສົນທະນານີ້ໄດ້.',
            search_chats: 'ຄົ້ນຫາການສົນທະນາ',
            date_today: 'ມື້ນີ້',
            date_last_7: '7 ມື້ຜ່ານມາ',
            date_earlier: 'ກ່ອນໜ້າ',
            just_now: 'ຫາກໍ່ນີ້',
            minute_ago: '1 ນາທີກ່ອນ',
            minutes_ago: '{n} ນາທີກ່ອນ',
            hour_ago: '1 ຊົ່ວໂມງກ່ອນ',
            hours_ago: '{n} ຊົ່ວໂມງກ່ອນ',
            day_ago: 'ມື້ວານນີ້',
            days_ago: '{n} ມື້ກ່ອນ',
            copy: 'ຄັດລອກ',
            listen: 'ຟັງ',
            cat_species: 'ຊະນິດພັນ',
            cat_champions: 'ຜູ້ນຳ',
            cat_library: 'ຫ້ອງສະໝຸດ',
            cat_stories: 'ເລື່ອງລາວ',
            cat_conservation: 'ອະນຸລັກ',
            starter_plants: 'ພືດໃດໃຊ້ໃນການເຮັດອາຫານລາວ?',
            starter_animals: 'ສັດຫຍັງແດ່ອາໄສຢູ່ໃນລາວ?',
            starter_medicinal: 'ພືດໃດຖືກໃຊ້ເປັນຢາ?',
            starter_birds: 'ນົກຊະນິດໃດພົບໃນລາວ?',
            starter_fish: 'ປາຊະນິດໃດອາໄສຢູ່ແມ່ນ້ຳຂອງ?',
            starter_matrix: 'ປຽບທຽບເຟິນ 3 ຊະນິດເປັນຕາຕະລາງ',
            starter_champions: 'ແຕ່ລະແຂວງມີຜູ້ນຳຈັກຄົນ?',
            starter_library: 'ຫ້ອງສະໝຸດເວົ້າແນວໃດກ່ຽວກັບວະນະກະສິກຳ?',
            starter_stories: 'ສະແດງເລື່ອງລາວກ່ຽວກັບການກະສິກຳ',
            starter_invasive: 'ຊະນິດພັນໃດເປັນຊະນິດພັນຮຸກຮານໃນລາວ?',
        },
    };
    let defaultPlaceholder = I18N.en.placeholder;

    function interfaceLang() {
        return getResponseLanguage() === 'lo' ? 'lo' : 'en';
    }
    function t(key) {
        return I18N[interfaceLang()][key] || I18N.en[key] || '';
    }

    // The dictionary lives inside this callback, out of reach of the
    // top-level delete handlers; publish the lookup rather than the object.
    window.pklT = t;

    /**
     * How long ago something happened, in the reader's language.
     *
     * Coarse on purpose: the exact second is never what is wanted, and past a
     * week a count of days stops meaning anything, so it stops there.
     */
    function relativeTime(iso) {
        const at = new Date(iso);
        if (isNaN(at)) return '';

        const seconds = Math.max(0, (Date.now() - at.getTime()) / 1000);
        const phrase = (one, many, count) =>
            (count === 1 ? t(one) : t(many)).replace('{n}', count);

        if (seconds < 60) return t('just_now');
        if (seconds < 3600) return phrase('minute_ago', 'minutes_ago', Math.floor(seconds / 60));
        if (seconds < 86400) return phrase('hour_ago', 'hours_ago', Math.floor(seconds / 3600));

        return phrase('day_ago', 'days_ago', Math.floor(seconds / 86400));
    }

    /** The three suggestions on the blank page, drawn once per load. */
    const STARTER_KEYS = [
        ['starter_plants', 'cat_species'],
        ['starter_animals', 'cat_species'],
        ['starter_medicinal', 'cat_species'],
        ['starter_birds', 'cat_species'],
        ['starter_fish', 'cat_species'],
        ['starter_matrix', 'cat_species'],
        ['starter_champions', 'cat_champions'],
        ['starter_library', 'cat_library'],
        ['starter_stories', 'cat_stories'],
        ['starter_invasive', 'cat_conservation'],
    ];
    const CATEGORY_MARKS = {
        cat_species: '#6fa96b',
        cat_champions: '#d9a441',
        cat_library: '#d9a441',
        cat_stories: '#9b7fc4',
        cat_conservation: '#5e93c4',
    };
    // Keys, not text: switching language translates them rather than drawing
    // a different three.
    const chosenStarters = STARTER_KEYS.slice().sort(() => Math.random() - 0.5).slice(0, 3);

    function renderStarters() {
        const host = document.getElementById('starters');
        if (!host) return;

        host.innerHTML = '';

        chosenStarters.forEach(([key, category]) => {
            const prompt = t(key);
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'w-full flex items-start gap-3 text-left px-4 py-3 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 hover:border-accent-500 transition-colors';
            card.innerHTML = `
                <span class="mt-1 shrink-0 w-6 h-6 rounded-lg flex items-center justify-center" style="background:${CATEGORY_MARKS[category]}22">
                    <span class="w-2.5 h-2.5 rotate-45 rounded-[2px]" style="background:${CATEGORY_MARKS[category]}"></span>
                </span>
                <span class="min-w-0">
                    <span class="block text-[11px] font-semibold tracking-wider text-zinc-500 dark:text-zinc-400">${escapeHtml(t(category))}</span>
                    <span class="block text-[15px] text-zinc-900 dark:text-zinc-100">${escapeHtml(prompt)}</span>
                </span>`;
            card.addEventListener('click', () => {
                input.value = prompt;
                input.focus();
                updateSendButton();
            });
            host.appendChild(card);
        });
    }

    /** Sort the history under Today / Last 7 days / Earlier. */
    function groupConversations() {
        const host = document.getElementById('chat-history');
        if (!host) return;

        const rows = Array.from(host.querySelectorAll('.js-conv'));
        host.querySelectorAll('.js-date-heading').forEach((el) => el.remove());

        const startOfToday = new Date();
        startOfToday.setHours(0, 0, 0, 0);
        const weekAgo = startOfToday.getTime() - 6 * 86400000;

        const bucketOf = (row) => {
            const at = new Date(row.dataset.updated).getTime();
            if (isNaN(at)) return 'date_earlier';
            if (at >= startOfToday.getTime()) return 'date_today';
            return at >= weekAgo ? 'date_last_7' : 'date_earlier';
        };

        let current = null;

        rows.forEach((row) => {
            if (row.classList.contains('hidden')) return;

            const bucket = bucketOf(row);
            if (bucket === current) return;

            current = bucket;
            const heading = document.createElement('div');
            heading.className = 'js-date-heading px-2 pt-4 pb-1 text-xs font-medium text-zinc-400 tracking-wider';
            heading.textContent = t(bucket);
            host.insertBefore(heading, row);
        });
    }

    function filterConversations(term) {
        const needle = term.trim().toLowerCase();

        document.querySelectorAll('.js-conv').forEach((row) => {
            row.classList.toggle('hidden', needle !== '' && !(row.dataset.title || '').includes(needle));
        });

        groupConversations();
    }

    /** Restamp every message; "3 minutes ago" goes stale as you read. */
    function refreshTimestamps() {
        document.querySelectorAll('[data-stamp]').forEach((el) => {
            el.textContent = relativeTime(el.dataset.stamp);
        });
    }

    function applyInterfaceLanguage() {
        const t = I18N[interfaceLang()];
        document.querySelectorAll('[data-i18n]').forEach((el) => {
            const k = el.dataset.i18n;
            if (t[k]) el.textContent = t[k];
        });
        document.querySelectorAll('[data-i18n-ph]').forEach((el) => {
            const k = el.dataset.i18nPh;
            if (t[k]) el.placeholder = t[k];
        });
        defaultPlaceholder = t.placeholder;

        renderStarters();
        groupConversations();
        refreshTimestamps();
    }

    function applyLanguageSwitch() {
        const cur = getResponseLanguage();
        document.querySelectorAll('.js-lang-opt').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.lang === cur);
        });
        applyInterfaceLanguage();
    }
    document.querySelectorAll('.js-lang-opt').forEach((btn) => {
        btn.addEventListener('click', () => {
            localStorage.setItem(LANG_KEY, btn.dataset.lang);
            applyLanguageSwitch();
        });
    });
    applyLanguageSwitch();

    // After the translations exist, not before: this runs inside the same
    // callback that declares them, and reaching for I18N above its
    // declaration threw, taking the whole page's setup down with it.
    refreshConversationTitle(currentConversationId);

    if (sessionStorage.getItem(DELETED_FLAG)) {
        sessionStorage.removeItem(DELETED_FLAG);

        // A courtesy message must never be able to break the page it greets.
        pkToast(t('deleted'), TRASH_SVG);
    }

    document.getElementById('chat-search')?.addEventListener('input', (event) => {
        filterConversations(event.target.value);
    });

    // "3 minutes ago" goes stale while the page sits open.
    setInterval(refreshTimestamps, 60000);

    let selectedImageFile = null;

    // Drag and Drop handlers
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        chatContainer.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        chatContainer.addEventListener(eventName, () => {
            if (!isStreaming) {
                dropOverlay.classList.remove('hidden');
                dropOverlay.classList.add('flex');
            }
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        chatContainer.addEventListener(eventName, () => {
            dropOverlay.classList.add('hidden');
            dropOverlay.classList.remove('flex');
        }, false);
    });

    chatContainer.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files[0] && files[0].type.startsWith('image/')) {
            handleImageFile(files[0]);
        }
    }, false);

    function handleImageFile(file) {
        selectedImageFile = file;
        const url = URL.createObjectURL(file);
        imagePreviewImg.src = url;
        imagePreview.classList.remove('hidden');
        
        // Force a reflow
        void imagePreview.offsetWidth;
        imagePreview.classList.remove('scale-95', 'opacity-0');
        imagePreview.classList.add('scale-100', 'opacity-100');
        
        input.placeholder = imagePlaceholder;
        updateSendButton();
    }

    // Scroll to bottom initially if there are messages
    messagesArea.scrollTop = messagesArea.scrollHeight;
    enforceNewTabLinks(messagesContainer);
    handleBrokenImages(messagesContainer);
    enhanceTables(messagesContainer);

    function updateSendButton() {
        const sendIcon = sendBtn.querySelector('.js-send-icon');
        const stopIcon = sendBtn.querySelector('.js-stop-icon');

        if (isStreaming) {
            // Turn the send button into a Stop button while generating.
            sendBtn.disabled = false;
            sendBtn.title = 'Stop generating';
            sendBtn.setAttribute('aria-label', 'Stop generating');
            sendIcon?.classList.add('hidden');
            stopIcon?.classList.remove('hidden');
        } else {
            sendBtn.title = 'Send';
            sendBtn.setAttribute('aria-label', 'Send');
            sendIcon?.classList.remove('hidden');
            stopIcon?.classList.add('hidden');
            sendBtn.disabled = (input.value.trim().length === 0 && !selectedImageFile);
        }
    }

    // Enable/Disable send button based on input or image
    input.addEventListener('input', updateSendButton);

    // Upload button triggers file input
    uploadBtn.addEventListener('click', () => imageInput.click());

    // Handle file selection
    const imagePlaceholder = 'Add species name for better results (optional)...';

    imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) handleImageFile(file);
    });

    // Remove selected image
    imagePreviewRemove.addEventListener('click', function () {
        imagePreview.classList.add('scale-95', 'opacity-0');
        imagePreview.classList.remove('scale-100', 'opacity-100');
        
        setTimeout(() => {
            selectedImageFile = null;
            imageInput.value = '';
            imagePreview.classList.add('hidden');
            imagePreviewImg.src = '';
            input.placeholder = defaultPlaceholder;
            updateSendButton();
        }, 200);
    });

    function scrollToBottom() {
        messagesArea.scrollTo({ top: messagesArea.scrollHeight, behavior: 'smooth' });
    }

    function showError(message) {
        errorText.textContent = message;
        errorMessage.classList.remove('hidden');
        setTimeout(() => errorMessage.classList.add('hidden'), 5000);
    }

    function hideWelcome() {
        if (welcomeMessage) welcomeMessage.style.display = 'none';
        document.getElementById('composer-spacer')?.classList.remove('hidden');
    }

    function addUserMessage(text, imageUrl) {
        hideWelcome();
        const div = document.createElement('div');
        div.className = 'chat-message relative flex justify-end w-full animate-fade-in';

        let content = '';
        if (imageUrl) {
            content += `
                <div class="mb-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <img src="${imageUrl}" alt="Uploaded image" class="max-h-80 w-auto object-contain" />
                </div>`;
        }
        if (text) {
            content += `<div class="px-1 py-0.5 whitespace-pre-wrap">${escapeHtml(text)}</div>`;
        }

        div.className = 'chat-message relative flex flex-col items-end w-full animate-fade-in';
        div.innerHTML = `<div class="max-w-[85%] md:max-w-[75%] px-4 py-3 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white text-[15px] leading-relaxed shadow-sm [overflow-wrap:anywhere] break-words">${content}</div>`;
        messagesContainer.appendChild(div);
        addUserMessageActions(div, text, new Date().toISOString());
        scrollToBottom();
    }

    function createAssistantBubble() {
        hideWelcome();
        const wrapper = document.createElement('div');
        wrapper.className = 'chat-message relative flex justify-start w-full animate-fade-in group';
        wrapper.innerHTML = `
            <div class="w-full">
                <div class="prose prose-zinc dark:prose-invert max-w-none text-[15px] leading-relaxed">
                    <div class="typing-indicator py-2 px-1"><img src="{{ asset('favicon-192.png') }}" alt="" class="pk-thinking w-7 h-7"></div>
                </div>
            </div>`;
        messagesContainer.appendChild(wrapper);
        scrollToBottom();
        return wrapper.querySelector('.prose');
    }

    function extractChartPayload(text) {
        const match = text.match(/\[CHART\]\s*(\{[\s\S]*?\})\s*\[\/CHART\]/i);
        if (!match) {
            return { chart: null, cleanedText: text };
        }

        let parsed = null;
        try {
            parsed = JSON.parse(match[1]);
        } catch (e) {
            return { chart: null, cleanedText: text };
        }

        const cleanedText = text.replace(match[0], '').trim();
        return { chart: parsed, cleanedText };
    }

    function renderChart(canvas, chart) {
        if (!canvas || !window.Chart || !chart || !Array.isArray(chart.labels) || !Array.isArray(chart.values)) {
            return;
        }

        const type = ['bar', 'line', 'pie', 'doughnut'].includes(String(chart.type || '').toLowerCase())
            ? String(chart.type).toLowerCase()
            : 'bar';

        const palette = ['#4f46e5', '#0ea5e9', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7', '#e11d48', '#3b82f6', '#16a34a'];
        const colors = chart.labels.map((_, index) => palette[index % palette.length]);

        new Chart(canvas, {
            type,
            data: {
                labels: chart.labels,
                datasets: [{
                    label: chart.title || 'Chart',
                    data: chart.values,
                    backgroundColor: type === 'line' ? 'rgba(79, 70, 229, 0.2)' : colors,
                    borderColor: type === 'line' ? '#4f46e5' : colors,
                    borderWidth: 2,
                    fill: type === 'line',
                    tension: 0.3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: type === 'pie' || type === 'doughnut' },
                },
            },
        });
    }

    function sanitizeHtml(html) {
        if (typeof DOMPurify !== 'undefined') {
            return DOMPurify.sanitize(html, {
                ADD_TAGS: ['canvas'],
                ALLOWED_TAGS: ['a', 'b', 'strong', 'em', 'i', 'code', 'pre', 'br', 'div', 'span', 'img', 'svg', 'path', 'ul', 'ol', 'li', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
                ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'src', 'alt', 'loading', 'referrerpolicy', 'data-fallback-img', 'viewBox', 'fill', 'stroke', 'stroke-linecap', 'stroke-linejoin', 'stroke-width', 'd', 'width', 'height'],
                ALLOW_DATA_ATTR: false,
            });
        }
        return html;
    }

    function renderAssistantContent(container, rawText) {
        const { chart, cleanedText } = extractChartPayload(rawText);
        const textHtml = cleanedText ? sanitizeHtml(formatText(cleanedText)) : '';

        if (!chart) {
            container.innerHTML = textHtml;
            return;
        }

        const chartTitle = escapeHtml(chart.title || 'Chart');
        container.innerHTML = sanitizeHtml(`
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 bg-white dark:bg-zinc-900">
                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">${chartTitle}</div>
                <div class="h-72"><canvas></canvas></div>
            </div>
            ${textHtml ? `<div class="mt-3">${textHtml}</div>` : ''}
        `);

        renderChart(container.querySelector('canvas'), chart);
    }

    // --- Text-to-speech with progressive (sentence-by-sentence) playback ---
    const SPEAKER_SVG = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M17.657 6.343a8 8 0 010 11.314M11 5L6 9H2v6h4l5 4V5z"></path></svg>';
    const STOP_AUDIO_SVG = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>';
    const SPINNER_SVG = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>';
    let currentAudio = null;
    let currentSpeakBtn = null;
    let speakToken = 0;

    function setSpeakState(btn, state) {
        if (!btn) return;
        btn.innerHTML = state === 'loading' ? SPINNER_SVG : (state === true ? STOP_AUDIO_SVG : SPEAKER_SVG);
    }
    function stopSpeaking() {
        speakToken++;
        if (currentAudio) { currentAudio.pause(); currentAudio = null; }
        if (currentSpeakBtn) { setSpeakState(currentSpeakBtn, false); currentSpeakBtn = null; }
    }
    function splitSpeechChunks(text, maxLen = 240) {
        const parts = text.match(/[^.!?။\n]+[.!?။\n]*/gu) || [text];
        const chunks = [];
        let cur = '';
        for (const p of parts) {
            if (cur && (cur + p).length > maxLen) { chunks.push(cur.trim()); cur = ''; }
            cur += p;
        }
        if (cur.trim()) chunks.push(cur.trim());
        return chunks.length ? chunks : [text];
    }
    function fetchSpeech(text) {
        return fetch('{{ route("tts") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ text: text.slice(0, 3000) }),
        }).then((r) => { if (!r.ok) throw new Error('tts'); return r.blob(); })
          .then((blob) => URL.createObjectURL(blob));
    }
    function speak(text, btn) {
        text = (text || '').trim();
        if (!text) return;
        if (btn && btn === currentSpeakBtn) { stopSpeaking(); return; }
        stopSpeaking();
        const token = ++speakToken;
        currentSpeakBtn = btn;
        setSpeakState(btn, 'loading');

        const chunks = splitSpeechChunks(text);
        let idx = 0;
        // Start the first sentence right away; prefetch the next while playing.
        let nextUrl = fetchSpeech(chunks[0]).catch(() => null);

        const finish = () => {
            if (token === speakToken) { setSpeakState(btn, false); currentSpeakBtn = null; currentAudio = null; }
        };
        const playNext = async () => {
            if (token !== speakToken) return;
            if (idx >= chunks.length) { finish(); return; }
            const url = await nextUrl;
            if (token !== speakToken) return;
            if (!url) { finish(); return; }
            idx++;
            nextUrl = idx < chunks.length ? fetchSpeech(chunks[idx]).catch(() => null) : Promise.resolve(null);
            const audio = new Audio(url);
            currentAudio = audio;
            setSpeakState(btn, true);
            audio.onended = () => { URL.revokeObjectURL(url); playNext(); };
            audio.onerror = () => { URL.revokeObjectURL(url); playNext(); };
            audio.play().catch(() => {});
        };
        playNext();
    }
    const COPY_SVG = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>';
    const CHECK_SVG = '<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';

    /**
     * The part of a reply worth reading aloud.
     *
     * Speech is linear, so everything that only works on a page is dropped: a
     * table read cell by cell is unfollowable, a URL read character by
     * character is noise, and an image has nothing to say. Link text is kept —
     * that is the sentence the writer meant.
     *
     * Reads the markdown rather than the rendered text, where a table's cells
     * and an auto-linked address are both just more words.
     */
    function speakableText(markdown) {
        return (markdown || '')
            .replace(/```[\s\S]*?```/g, '')
            .replace(/`([^`]*)`/g, '$1')
            .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
            .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
            .replace(/\[CHART\][\s\S]*?\[\/CHART\]/gi, '')
            .replace(/https?:\/\/\S+/g, '')
            .split('\n')
            // Any row of a table, header and separator included.
            .filter((line) => !line.trimStart().startsWith('|'))
            .map((line) => line
                .replace(/^\s*#{1,6}\s*/, '')
                .replace(/^\s*>\s*/, '')
                .replace(/^\s*([-*+]|\d+[.)])\s+/, '')
                .replace(/\*\*|__|\*|_/g, '')
                .trim())
            .filter((line) => line !== '')
            .join('\n')
            .trim();
    }

    function getRawText(proseEl) {
        const raw = proseEl.closest('.chat-message')?.querySelector('.js-assistant-raw');
        if (raw && typeof raw.value === 'string' && raw.value.trim()) return raw.value;
        if (proseEl.dataset.raw) return proseEl.dataset.raw;
        return proseEl.textContent || '';
    }
    function copyMessage(proseEl, btn) {
        copyText(getRawText(proseEl).trim(), btn);
    }

    function copyText(text, btn) {
        if (!text) return;
        const done = () => {
            btn.innerHTML = CHECK_SVG;
            setTimeout(() => { btn.innerHTML = COPY_SVG; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
        } else {
            fallbackCopy(text, done);
        }
    }
    function fallbackCopy(text, done) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
    }
    function addMessageActions(proseEl) {
        if (!proseEl || proseEl.dataset.actionsAdded) return;
        if (!(proseEl.textContent || '').trim()) return;
        proseEl.dataset.actionsAdded = '1';

        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 mt-1.5';

        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.title = t('copy');
        copyBtn.className = 'text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors';
        copyBtn.innerHTML = COPY_SVG;
        copyBtn.addEventListener('click', () => copyMessage(proseEl, copyBtn));

        const speakBtn = document.createElement('button');
        speakBtn.type = 'button';
        speakBtn.title = t('listen');
        speakBtn.className = 'js-speak-btn text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors';
        speakBtn.innerHTML = SPEAKER_SVG;
        speakBtn.addEventListener('click', () => {
            const spoken = speakableText(getRawText(proseEl));

            if (!spoken) {
                pkToast(window.pklT?.('nothing_to_read') || 'There is nothing to read aloud here.');
                return;
            }

            speak(spoken, speakBtn);
        });

        row.appendChild(copyBtn);
        row.appendChild(speakBtn);
        row.appendChild(timestampEl(proseEl.closest('.chat-message')?.dataset.at));
        proseEl.parentElement.appendChild(row);

        showConversationFooter();
    }

    /** When a message was written, sat beside its actions. */
    function timestampEl(iso) {
        const stamp = document.createElement('span');
        stamp.className = 'ml-1 text-xs text-zinc-400 dark:text-zinc-500';
        stamp.dataset.stamp = iso || new Date().toISOString();
        stamp.textContent = relativeTime(stamp.dataset.stamp);

        return stamp;
    }

    /** Copy and a stamp under the reader's own message. */
    function addUserMessageActions(bubbleEl, text, iso) {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-end gap-2 mt-1.5';

        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.title = t('copy');
        copyBtn.className = 'text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors';
        copyBtn.innerHTML = COPY_SVG;
        copyBtn.addEventListener('click', () => copyText(text, copyBtn));

        row.appendChild(copyBtn);
        row.appendChild(timestampEl(iso));
        bubbleEl.appendChild(row);
    }

    function showConversationFooter() {
        const footer = document.getElementById('conversation-footer');
        if (!footer) return;

        footer.classList.remove('hidden');
        // New turns append after it, so move it back to the end — a sign-off
        // stranded mid-thread reads as though the conversation ended there.
        messagesContainer.appendChild(footer);
    }

    function renderExistingAssistantMessages() {
        document.querySelectorAll('.chat-message').forEach((messageEl) => {
            const rawEl = messageEl.querySelector('.js-assistant-raw');
            const renderedEl = messageEl.querySelector('.js-assistant-rendered');
            if (!rawEl || !renderedEl) {
                return;
            }

            renderAssistantContent(renderedEl, rawEl.value || '');
            addMessageActions(renderedEl);
        });

        document.querySelectorAll('.js-user-message').forEach((messageEl) => {
            if (messageEl.dataset.actionsAdded) return;
            messageEl.dataset.actionsAdded = '1';

            addUserMessageActions(
                messageEl,
                messageEl.querySelector('.js-user-text')?.textContent || '',
                messageEl.dataset.at
            );
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function stopGeneration() {
        if (abortController) abortController.abort();
    }

    async function sendMessage(message) {
        if (isStreaming) return;
        isStreaming = true;
        abortController = new AbortController();
        updateSendButton();
        input.value = '';

        // Capture image before clearing
        const imageFile = selectedImageFile;
        const imageUrl = imageFile ? URL.createObjectURL(imageFile) : null;

        // Clear image selection
        selectedImageFile = null;
        imageInput.value = '';
        imagePreview.classList.add('hidden', 'scale-95', 'opacity-0');
        imagePreview.classList.remove('scale-100', 'opacity-100');
        imagePreviewImg.src = '';
        input.placeholder = defaultPlaceholder;

        addUserMessage(message, imageUrl);
        const bubble = createAssistantBubble();

        try {
            const formData = new FormData();
            formData.append('message', message);
            formData.append('conversation_id', currentConversationId || '');
            const respLang = getResponseLanguage();
            if (respLang === 'en' || respLang === 'lo') {
                formData.append('response_language', respLang);
            }
            if (imageFile) {
                formData.append('image', imageFile);
            }

            const response = await fetch('{{ route("chat.send") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'text/event-stream' },
                body: formData,
                signal: abortController.signal,
            });

            if (!response.ok) {
                let errorMsg = 'Stream failed';
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.message || errorMsg;
                } catch (e) {}
                throw new Error(errorMsg);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullText = '';
            let buffer = '';
            bubble.innerHTML = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop();

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed.startsWith('data: ')) continue;
                    const data = trimmed.slice(6);
                    if (data === '[DONE]') continue;

                    try {
                        const parsed = JSON.parse(data);
                        if (parsed.delta) {
                            fullText += parsed.delta;
                            renderAssistantContent(bubble, fullText);
                            enforceNewTabLinks(messagesContainer);
                            handleBrokenImages(messagesContainer);
                            enhanceTables(messagesContainer);
                            scrollToBottom();
                        }
                        if (parsed.conversation_id && !currentConversationId) {
                            currentConversationId = parsed.conversation_id;
                        }
                    } catch (e) {}
                }
            }

            if (fullText) {
                bubble.dataset.raw = fullText;
                addMessageActions(bubble);
                const saveResponse = await fetch('{{ route("chat.save-response") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ content: fullText, conversation_id: currentConversationId }),
                });

                let saveData = null;
                try {
                    saveData = await saveResponse.json();
                } catch (e) {}

                if (saveData?.conversation_id && !currentConversationId) {
                    currentConversationId = saveData.conversation_id;
                }

                // If this started as a new chat, navigate to the created conversation once.
                if (currentConversationId && window.location.pathname !== `/chat/${currentConversationId}`) {
                    window.location.href = `/chat/${currentConversationId}`;
                } else {
                    refreshConversationTitle(currentConversationId);
                }
            }
        } catch (error) {
            // User pressed Stop — remove the pending bubble, no error toast.
            if (error.name === 'AbortError') {
                bubble.parentElement.parentElement.remove();
            } else {
                console.error('Chat error:', error);
                showError(error.message || 'Something went wrong.');
                bubble.parentElement.parentElement.remove();
            }
        } finally {
            isStreaming = false;
            abortController = null;
            updateSendButton();
            input.focus();
        }
    }

    /**
     * Turn markdown pipe tables into real tables.
     *
     * Runs before newlines become <br>, while rows are still on their own
     * lines. A wide matrix scrolls inside its own container rather than
     * stretching the message.
     */
    function formatTables(html) {
        const isSeparator = (line) => /^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/.test(line.trim());
        const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map((cell) => cell.trim());

        const lines = html.split('\n');
        const out = [];

        for (let i = 0; i < lines.length; i++) {
            const header = lines[i];
            const separator = lines[i + 1];

            const looksLikeTable = header.trim().startsWith('|')
                && typeof separator === 'string'
                && isSeparator(separator)
                && cells(header).length > 1;

            if (!looksLikeTable) {
                out.push(header);
                continue;
            }

            const headings = cells(header);
            const body = [];
            let row = i + 2;

            while (row < lines.length && lines[row].trim().startsWith('|')) {
                body.push(cells(lines[row]));
                row++;
            }

            // Styled from the stylesheet, not utility classes: these are built
            // at runtime, so anything the CSS build never saw would not exist.
            const th = headings.map((cell) => `<th>${cell}</th>`).join('');

            const tr = body
                .map((cols) => '<tr>' + headings
                    .map((_, index) => `<td>${cols[index] ?? ''}</td>`)
                    .join('') + '</tr>')
                .join('');

            out.push(
                '<div class="pk-table-wrap">'
                + '<div class="pk-table-scroll">'
                + '<table class="pk-table">'
                + `<thead><tr>${th}</tr></thead><tbody>${tr}</tbody>`
                + '</table></div></div>'
            );

            i = row - 1;
        }

        return out.join('\n');
    }

    /**
     * Turn "- item" and "1. item" runs into real lists.
     *
     * Without this every bullet stayed a literal dash on its own line, which
     * is how a tidy answer arrived looking like plain text on the web while
     * the app rendered it properly.
     */
    function formatLists(html) {
        const out = [];
        let items = null;
        let ordered = false;
        let inPre = false;
        let blankHeld = false;

        const flush = () => {
            if (!items) return;
            const tag = ordered ? 'ol' : 'ul';
            out.push(`<${tag} class="pk-list">${items.map((item) => `<li>${item}</li>`).join('')}</${tag}>`);
            items = null;
        };

        const releaseBlank = () => {
            if (blankHeld) out.push('');
            blankHeld = false;
        };

        for (const line of html.split('\n')) {
            // A dash opening a line of code is not a bullet.
            if (/<pre[\s>]/.test(line)) inPre = true;

            if (inPre) {
                flush();
                releaseBlank();
                out.push(line);
                if (/<\/pre>/.test(line)) inPre = false;
                continue;
            }

            const bullet = line.match(/^\s{0,3}[-*+]\s+(.+)$/);
            const numbered = line.match(/^\s{0,3}\d+[.)]\s+(.+)$/);

            if (bullet || numbered) {
                const isOrdered = Boolean(numbered);
                if (items && ordered !== isOrdered) flush();
                ordered = isOrdered;
                items = items || [];
                items.push((bullet || numbered)[1]);
                blankHeld = false;
                continue;
            }

            if (items) {
                // A blank line may just be spacing between items, so hold it
                // until the next line says whether the list really ended.
                if (line.trim() === '') {
                    blankHeld = true;
                    continue;
                }

                // A line running straight on from an item belongs to it — the
                // species link under each entry, which otherwise cut the list
                // in two and restarted the numbering at 1.
                if (!blankHeld) {
                    items[items.length - 1] += `<br>${line.trim()}`;
                    continue;
                }
            }

            flush();
            releaseBlank();
            out.push(line);
        }

        flush();
        releaseBlank();

        return out.join('\n');
    }

    function formatText(text) {
        text = text.replace(/https:\/\/species\.phakhaolao\.la\/species\/(\d+)/gi, 'https://species.phakhaolao.la/search/specie_details/$1');
        let html = escapeHtml(text);
        html = html.replace(/```([\s\S]*?)```/g, '<pre class="bg-zinc-900 text-zinc-100 p-3 rounded-lg my-2 overflow-x-auto text-sm"><code>$1</code></pre>');
        html = html.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1" loading="lazy" referrerpolicy="no-referrer" class="pk-chat-img" data-fallback-img />');
        html = html.replace(/(^|[\s>])(https?:\/\/[^\s<>"']+\.(?:png|jpe?g|gif|webp)(?:\?[^\s<>"']*)?)(?=$|[\s<])/gmi, (full, prefix, url) => {
            const clean = String(url).replace(/[.,;:!?)]+$/g, '');
            return `${prefix}<img src="${clean}" alt="species image" loading="lazy" referrerpolicy="no-referrer" class="pk-chat-img" data-fallback-img />`;
        });
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-accent-600 dark:text-accent-400 underline">$1</a>');
        html = html.replace(/(^|[\s>])(https?:\/\/[^\s<>"']+)(?=$|[\s<])/gmi, (full, prefix, url) => {
            const clean = String(url).replace(/[.,;:!?)]+$/g, '');
            if (/\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(clean)) {
                return full;
            }
            return `${prefix}<a href="${clean}" target="_blank" rel="noopener noreferrer" class="text-accent-600 dark:text-accent-400 underline">${clean}</a>`;
        });
        html = html.replace(/^\s{0,3}#{4,6}\s+(.+)$/gm, '<div class="font-semibold mt-3 mb-1">$1</div>');
        html = html.replace(/^\s{0,3}###\s+(.+)$/gm, '<div class="font-semibold text-base mt-3 mb-1">$1</div>');
        html = html.replace(/^\s{0,3}##\s+(.+)$/gm, '<div class="font-semibold text-lg mt-3 mb-1">$1</div>');
        html = html.replace(/^\s{0,3}#\s+(.+)$/gm, '<div class="font-bold text-lg mt-3 mb-1">$1</div>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Single asterisks are italics, but only after bold has been consumed.
        // Requiring non-space either side keeps "2 * 3 * 4" as arithmetic.
        html = html.replace(/(^|[^*\w])\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/g, '$1<em>$2</em>');
        html = html.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 rounded-md bg-zinc-200 dark:bg-zinc-700 text-sm font-mono">$1</code>');
        html = formatTables(html);
        html = formatLists(html);
        html = html.replace(/\n/g, '<br>');
        // Group consecutive images into a compact thumbnail grid (single image stays medium).
        html = html.replace(/(?:<img[^>]*class="pk-chat-img[^>]*>(?:\s*<br>\s*)?)+/g, (run) => {
            let imgs = run.replace(/<br>/g, '').trim();
            const count = (imgs.match(/<img/g) || []).length;
            const border = 'rounded-lg border border-zinc-200 dark:border-zinc-700';

            if (count === 1) {
                imgs = imgs.replace(/class="pk-chat-img[^"]*"/, `class="w-full h-auto max-h-64 object-contain ${border}"`);
                return `<div class="my-3 max-w-[16rem]">${imgs}</div>`;
            }

            imgs = imgs.replace(/class="pk-chat-img[^"]*"/g, `class="w-full h-24 sm:h-28 object-cover ${border}"`);
            return `<div class="grid grid-cols-3 sm:grid-cols-4 gap-1.5 my-3 max-w-md">${imgs}</div>`;
        });
        return html;
    }

    /**
     * Give every rendered table a Copy and CSV button.
     *
     * Added to the DOM afterwards rather than in the markup, since the
     * sanitizer strips buttons.
     */
    function enhanceTables(root) {
        (root || document).querySelectorAll('.pk-table-wrap:not([data-enhanced])').forEach((wrap) => {
            wrap.setAttribute('data-enhanced', '1');

            const table = wrap.querySelector('table');
            if (!table) return;

            const bar = document.createElement('div');
            bar.className = 'pk-table-bar';

            const button = (label, title) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = label;
                b.title = title;
                b.className = 'pk-table-btn';
                return b;
            };

            const copyBtn = button('⧉ Copy', 'Copy the table (paste into Excel or Sheets)');
            const excelBtn = button('↓ Excel', 'Download as a formatted Excel file');
            const imageBtn = button('↓ Image', 'Download the table as a PNG image');

            copyBtn.addEventListener('click', async () => {
                const ok = await copyTable(table);
                copyBtn.textContent = ok ? '✓ Copied' : 'Failed';
                setTimeout(() => { copyBtn.textContent = '⧉ Copy'; }, 1500);
            });

            excelBtn.addEventListener('click', async () => {
                const original = excelBtn.textContent;
                excelBtn.textContent = 'Preparing…';
                excelBtn.disabled = true;

                const ok = await downloadTableXlsx(table);

                excelBtn.textContent = ok ? original : 'Failed';
                excelBtn.disabled = false;

                if (!ok) setTimeout(() => { excelBtn.textContent = original; }, 1500);
            });

            imageBtn.addEventListener('click', () => downloadTablePng(table));

            bar.appendChild(copyBtn);
            bar.appendChild(excelBtn);
            bar.appendChild(imageBtn);
            wrap.insertBefore(bar, wrap.firstChild);
        });
    }

    /**
     * @returns {Array<Array<string>>} rows of trimmed cell text, header first
     */
    function tableRows(table) {
        return [...table.querySelectorAll('tr')].map((tr) =>
            [...tr.querySelectorAll('th,td')].map((cell) => cell.textContent.replace(/\s+/g, ' ').trim())
        );
    }

    /**
     * Copies as tab-separated text plus the table's own HTML, so a paste lands
     * as real cells in a spreadsheet and as a table in a document.
     */
    async function copyTable(table) {
        const tsv = tableRows(table).map((row) => row.join('\t')).join('\n');

        try {
            if (navigator.clipboard && window.ClipboardItem) {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/plain': new Blob([tsv], { type: 'text/plain' }),
                    'text/html': new Blob([table.outerHTML], { type: 'text/html' }),
                })]);
                return true;
            }

            await navigator.clipboard.writeText(tsv);
            return true;
        } catch (error) {
            try {
                await navigator.clipboard.writeText(tsv);
                return true;
            } catch (_) {
                return false;
            }
        }
    }

    /**
     * Builds the workbook server-side, so the download keeps the header,
     * borders and column widths rather than arriving as bare values.
     */
    async function downloadTableXlsx(table) {
        try {
            const response = await fetch('{{ route('chat.export-table') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content ?? '',
                },
                body: JSON.stringify({ rows: tableRows(table), title: 'Table' }),
            });

            if (!response.ok) return false;

            const blob = await response.blob();
            saveBlob(blob, `phakhaolao-table-${new Date().toISOString().slice(0, 10)}.xlsx`);
            return true;
        } catch (error) {
            return false;
        }
    }

    function saveBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    /**
     * Draws the table onto a canvas and saves it as a PNG.
     *
     * Painted by hand rather than with a screenshot library so the image is
     * always light-on-white and legible, whatever theme the page is in, and so
     * nothing extra has to be loaded.
     */
    function downloadTablePng(table) {
        const rows = tableRows(table);
        if (!rows.length) return;

        const SCALE = 2;                 // render at 2x for a sharp image
        const PAD = 12;                  // padding inside a cell
        const LINE = 20;                 // line height
        const MAX_COL = 260;             // widest a column may get
        const MIN_COL = 90;
        const FONT = '13px system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Lao", sans-serif';
        const BOLD = 'bold ' + FONT;

        const measurer = document.createElement('canvas').getContext('2d');
        const columnCount = Math.max(...rows.map((row) => row.length));

        // Column widths from the widest single word, so wrapping stays sane.
        const widths = [];
        for (let c = 0; c < columnCount; c++) {
            let widest = MIN_COL;
            rows.forEach((row, r) => {
                measurer.font = r === 0 ? BOLD : FONT;
                const text = row[c] ?? '';
                widest = Math.max(widest, Math.min(measurer.measureText(text).width + PAD * 2, MAX_COL));
            });
            widths.push(Math.ceil(widest));
        }

        // Split a word with no spaces — a URL, typically — that cannot fit.
        const breakWord = (word, width) => {
            const pieces = [];
            let piece = '';

            for (const char of word) {
                if (measurer.measureText(piece + char).width > width && piece) {
                    pieces.push(piece);
                    piece = char;
                } else {
                    piece += char;
                }
            }

            if (piece) pieces.push(piece);
            return pieces;
        };

        const wrap = (text, font, width) => {
            measurer.font = font;
            const lines = [];
            let line = '';

            String(text).split(/\s+/).forEach((word) => {
                if (measurer.measureText(word).width > width) {
                    if (line) { lines.push(line); line = ''; }
                    const pieces = breakWord(word, width);
                    lines.push(...pieces.slice(0, -1));
                    line = pieces[pieces.length - 1] ?? '';
                    return;
                }

                const candidate = line ? line + ' ' + word : word;
                if (measurer.measureText(candidate).width <= width || !line) {
                    line = candidate;
                } else {
                    lines.push(line);
                    line = word;
                }
            });

            if (line) lines.push(line);
            return lines.length ? lines : [''];
        };

        // Wrap every cell first, so row heights are known before drawing.
        const wrapped = rows.map((row, r) => {
            const font = r === 0 ? BOLD : FONT;
            return Array.from({ length: columnCount }, (_, c) =>
                wrap(row[c] ?? '', font, widths[c] - PAD * 2));
        });
        const heights = wrapped.map((cells) =>
            Math.max(...cells.map((lines) => lines.length)) * LINE + PAD * 2);

        const totalWidth = widths.reduce((sum, w) => sum + w, 0);
        const totalHeight = heights.reduce((sum, h) => sum + h, 0);

        const canvas = document.createElement('canvas');
        canvas.width = totalWidth * SCALE;
        canvas.height = totalHeight * SCALE;
        const ctx = canvas.getContext('2d');
        ctx.scale(SCALE, SCALE);
        ctx.textBaseline = 'top';

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, totalWidth, totalHeight);

        let y = 0;
        wrapped.forEach((cells, r) => {
            const height = heights[r];

            if (r === 0) {
                ctx.fillStyle = '#efefe6';
                ctx.fillRect(0, y, totalWidth, height);
            } else if (r % 2 === 0) {
                ctx.fillStyle = '#f7f7f0';
                ctx.fillRect(0, y, totalWidth, height);
            }

            let x = 0;
            cells.forEach((lines, c) => {
                ctx.strokeStyle = '#dcdcd0';
                ctx.lineWidth = 1;
                ctx.strokeRect(Math.round(x) + 0.5, Math.round(y) + 0.5, widths[c], height);

                ctx.fillStyle = r === 0 ? '#22261d' : '#2c3126';
                ctx.font = (r === 0 || c === 0) ? BOLD : FONT;
                lines.forEach((line, index) => {
                    ctx.fillText(line, x + PAD, y + PAD + index * LINE);
                });

                x += widths[c];
            });

            y += height;
        });

        canvas.toBlob((blob) => {
            if (blob) saveBlob(blob, `phakhaolao-table-${new Date().toISOString().slice(0, 10)}.png`);
        }, 'image/png');
    }

    function enforceNewTabLinks(root) {
        (root || document).querySelectorAll('.prose a[href]').forEach((anchor) => {
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener noreferrer');
        });
    }

    function handleBrokenImages(root) {
        (root || document).querySelectorAll('img[data-fallback-img]').forEach((img) => {
            if (img.dataset.fallbackBound) return;
            img.dataset.fallbackBound = '1';
            img.addEventListener('error', function () {
                const wrapper = this.parentElement;
                if (!wrapper) return;
                const url = this.src;
                wrapper.textContent = '';
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.target = '_blank';
                anchor.rel = 'noopener noreferrer';
                anchor.className = 'flex items-center gap-2 p-3 text-sm text-zinc-500 dark:text-zinc-400 hover:text-accent-600 dark:hover:text-accent-400';
                anchor.textContent = 'Image unavailable';
                wrapper.appendChild(anchor);
            });
        });
    }

    renderExistingAssistantMessages();
    enforceNewTabLinks(messagesContainer);
    handleBrokenImages(messagesContainer);
    enhanceTables(messagesContainer);

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        // While generating, the button acts as a Stop button.
        if (isStreaming) {
            stopGeneration();
            return;
        }
        const msg = input.value.trim();
        if (msg || selectedImageFile) sendMessage(msg);
    });

    // --- Voice input (speech-to-text via Google STT) ---
    const micBtn = document.getElementById('mic-btn');
    const MIC_SVG = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-14 0m7 7v3m-4 0h8M12 1a3 3 0 00-3 3v6a3 3 0 006 0V4a3 3 0 00-3-3z"></path></svg>';
    const MIC_STOP_SVG = '<svg class="w-5 h-5 text-red-500 animate-pulse" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>';
    const MIC_SPINNER_SVG = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>';
    let mediaRecorder = null;
    let audioChunks = [];

    function setMicState(state) {
        if (!micBtn) return;
        micBtn.disabled = state === 'transcribing';
        micBtn.innerHTML = state === 'recording' ? MIC_STOP_SVG : (state === 'transcribing' ? MIC_SPINNER_SVG : MIC_SVG);
        micBtn.title = state === 'recording' ? 'Stop recording' : 'Voice input';
    }
    async function startRecording() {
        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (e) {
            showError('Microphone access is required for voice input.');
            return;
        }
        audioChunks = [];
        const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
        mediaRecorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
        mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) audioChunks.push(e.data); };
        mediaRecorder.onstop = () => {
            stream.getTracks().forEach((t) => t.stop());
            transcribeAudio(new Blob(audioChunks, { type: 'audio/webm' }));
        };
        mediaRecorder.start();
        setMicState('recording');
    }
    function transcribeAudio(blob) {
        if (!blob || !blob.size) { setMicState('idle'); return; }
        setMicState('transcribing');
        const fd = new FormData();
        fd.append('audio', blob, 'recording.webm');
        // Send the switch value; for "auto" the backend detects Lao vs English.
        fd.append('language', getResponseLanguage());
        fetch('{{ route("transcribe") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        }).then((r) => r.json()).then((data) => {
            setMicState('idle');
            const text = (data && data.text) ? String(data.text).trim() : '';
            if (text) {
                input.value = input.value.trim() ? (input.value.trim() + ' ' + text) : text;
                input.focus();
                updateSendButton();
            } else {
                showError('Could not transcribe the audio. Please try again.');
            }
        }).catch(() => { setMicState('idle'); showError('Voice transcription failed.'); });
    }
    if (micBtn) {
        micBtn.addEventListener('click', () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
            } else {
                startRecording();
            }
        });
    }

    deleteModalCancel.addEventListener('click', closeDeleteModal);
    deleteModalConfirm.addEventListener('click', async () => {
        if (!pendingDeleteConversationId) return;
        await deleteConversation(pendingDeleteConversationId);
        closeDeleteModal();
    });
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) closeDeleteModal();
    });

    input.focus();
});
</script>

<style>
@keyframes fade-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-in { animation: fade-in 0.3s ease-out forwards; }
@keyframes fade-in-up { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-in-up { animation: fade-in-up 0.5s ease-out forwards; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #dcdcd0; border-radius: 20px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #2c3126; }
/* Break long URLs/words so a message never forces horizontal scroll. */
.prose, .prose a, .whitespace-pre-wrap { overflow-wrap: anywhere; word-break: break-word; }
.prose pre { overflow-x: auto; max-width: 100%; }
/* Tables, styled here rather than with utility classes: the markup is built at
   runtime, so any class the CSS build never saw would not exist — which is how
   dark rows ended up white. */
.pk-table-wrap {
    max-width: 100%;
    margin: 0.75rem 0;
    border: 1px solid #dcdcd0;
    border-radius: 0.5rem;
    overflow: hidden;
    background: #ffffff;
}
.dark .pk-table-wrap { border-color: #2c3126; background: #1a1d16; }

.pk-table-bar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: #f7f7f0;
    border-bottom: 1px solid #dcdcd0;
}
.dark .pk-table-bar { background: #22261d; border-bottom-color: #2c3126; }

.pk-table-btn {
    font-size: 0.75rem;
    line-height: 1rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    color: #5e6153;
    background: transparent;
    transition: background-color .15s, color .15s;
    white-space: nowrap;
}
.pk-table-btn:hover { background: #dcdcd0; color: #1a1d16; }
.dark .pk-table-btn { color: #9a9c8c; }
.dark .pk-table-btn:hover { background: #2c3126; color: #f7f7f0; }

.pk-table-scroll { overflow-x: auto; }

.pk-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    line-height: 1.35;
}
.pk-table th, .pk-table td {
    padding: 0.5rem 0.75rem;
    text-align: left;
    vertical-align: top;
    /* Breaking anywhere shreds a narrow column into single letters. */
    overflow-wrap: break-word;
    word-break: normal;
    min-width: 8rem;
}
.pk-table th:first-child, .pk-table td:first-child { min-width: 10rem; }
.pk-table th {
    font-weight: 600;
    color: #2c3126;
    background: #efefe6;
    border-bottom: 1px solid #c8c9bc;
}
.dark .pk-table th { color: #dcdcd0; background: #22261d; border-bottom-color: #5e6153; }
.pk-table td { color: #22261d; border-top: 1px solid #dcdcd0; }
.dark .pk-table td { color: #c8c9bc; border-top-color: #2c3126; }
.pk-table td:first-child { font-weight: 500; color: #1a1d16; }
.dark .pk-table td:first-child { color: #f7f7f0; }
.pk-table th + th, .pk-table td + td { border-left: 1px solid #dcdcd0; }
.dark .pk-table th + th, .dark .pk-table td + td { border-left-color: #2c3126; }
.pk-table tbody tr:nth-child(even) { background: #f7f7f0; }
.dark .pk-table tbody tr:nth-child(even) { background: #1e2119; }
.pk-table a { overflow-wrap: anywhere; }
@keyframes pk-toast-in { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }
.pk-toast { animation: pk-toast-in .22s ease-out; }

/* The mark breathes while a reply is composed. Dots said something was
   happening but nothing about who was doing it. */
@keyframes pk-breathe {
    0%, 100% { opacity: .35; transform: scale(.88); }
    50% { opacity: 1; transform: scale(1); }
}
.pk-thinking { animation: pk-breathe 1.8s ease-in-out infinite; }
@media (prefers-reduced-motion: reduce) {
    .pk-thinking { animation: none; opacity: .7; }
}

/* Lists, for the same reason as the tables above: built at runtime. */
.pk-list { margin: 0.5rem 0; padding-left: 1.5rem; }
.pk-list li { margin: 0.15rem 0; }
ul.pk-list { list-style: disc; }
ol.pk-list { list-style: decimal; }

/* Nothing eases while the theme changes, so the whole page turns at once. */
.pk-theme-switching,
.pk-theme-switching *,
.pk-theme-switching *::before,
.pk-theme-switching *::after {
    transition: none !important;
}

/* Nothing marks focus on these inputs: the browser's default outline is its
   own accent colour, and no replacement was wanted. The cursor sits in the
   field, which is the only signal left. */
#message-input:focus,
#chat-search:focus {
    outline: none;
}

/* Answer-language switch active state (build-independent). */
.js-lang-opt { color: #7c7e6f; }
.js-lang-opt.is-active { background: #ffffff; color: #1a1d16; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
.dark .js-lang-opt { color: #9a9c8c; }
.dark .js-lang-opt.is-active { background: #2c3126; color: #ffffff; }
</style>
@endsection

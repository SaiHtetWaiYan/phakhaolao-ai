@extends('layouts.app')

@section('title', 'Phakhaolao AI')

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
                New Chat
            </a>
        </div>

        {{-- History List --}}
        <div class="flex-1 overflow-y-auto px-4 py-2 space-y-1 custom-scrollbar">
            <div class="px-2 py-2 text-xs font-medium text-zinc-400 uppercase tracking-wider">Recent</div>
            @foreach($conversations as $conv)
                <div class="group relative flex items-center">
                    <a href="{{ route('chat', $conv->id) }}" 
                       class="flex-1 flex items-center gap-3 pl-2 pr-10 py-2 text-sm text-left {{ (isset($currentConversation) && $currentConversation->id === $conv->id) ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} rounded-md transition-colors min-w-0">
                        <svg class="shrink-0 w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                        <span class="truncate block min-w-0">{{ Str::limit($conv->title, 18) }}</span>
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
            <button data-theme-toggle class="w-full flex items-center gap-3 px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors text-zinc-600 dark:text-zinc-400">
                <div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center">
                    <svg data-theme-icon-dark class="hidden w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg data-theme-icon-light class="hidden w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                </div>
                <span class="text-sm font-medium">Switch Theme</span>
            </button>
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-medium text-xs">
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
        <div id="drop-overlay" class="absolute inset-0 z-50 bg-indigo-600/10 backdrop-blur-[2px] border-2 border-dashed border-indigo-500 rounded-3xl m-4 hidden flex-col items-center justify-center transition-all duration-300 pointer-events-none">
            <div class="bg-white dark:bg-zinc-900 px-8 py-6 rounded-3xl shadow-2xl flex flex-col items-center gap-4 transform scale-110">
                <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/50 rounded-2xl flex items-center justify-center text-indigo-600 dark:text-indigo-400">
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
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Model:</span>
                <button class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-sm font-semibold text-zinc-800 dark:text-zinc-200 transition-colors">
                    <span>Phakhaolao 1.0</span>
                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
            </div>
            <div class="flex items-center gap-2">
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
                <div id="welcome-message" class="flex flex-col items-center justify-center py-12 md:py-20 text-center animate-fade-in-up" style="{{ !empty($messages) ? 'display: none;' : '' }}">
                    <div class="mb-8">
                        <img src="{{ asset('images/logo.webp') }}" alt="Phakhaolao AI" class="h-16 md:h-20 w-auto dark:filter-[invert(1)_hue-rotate(180deg)]">
                    </div>
                    <h2 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">How can I help you today?</h2>
                    <p class="text-zinc-500 dark:text-zinc-400 max-w-md">Ask about Laos plants, animals, uses, habitats, and local species data from the PhaKhaoLao knowledge base.</p>
                </div>

                {{-- Render Existing Messages --}}
                @if(!empty($messages))
                    @foreach($messages as $msg)
                        @if($msg['role'] === 'user')
                            <div class="chat-message relative flex justify-end w-full animate-fade-in">
                                <div class="max-w-[85%] md:max-w-[75%] px-4 py-3 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white text-[15px] leading-relaxed shadow-sm">
                                    @if(!empty($msg['meta']['image_url']))
                                        <div class="mb-2 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                            <img src="{{ $msg['meta']['image_url'] }}" alt="Uploaded image" class="max-h-80 w-auto object-contain" />
                                        </div>
                                    @endif
                                    @if($msg['content'])
                                        <div class="px-1 py-0.5 whitespace-pre-wrap">{{ $msg['content'] }}</div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="chat-message relative flex justify-start w-full gap-4 animate-fade-in group">
                                <div class="shrink-0 w-8 h-8 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shadow-sm overflow-hidden">
                                    <img src="{{ asset('favicon-192.png') }}" alt="AI" class="w-6 h-6">
                                </div>
                                <div class="max-w-[85%] md:max-w-[85%]">
                                    <textarea class="hidden js-assistant-raw">{{ $msg['content'] ?? '' }}</textarea>
                                    <div class="prose prose-zinc dark:prose-invert max-w-none text-[15px] leading-relaxed js-assistant-rendered"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="h-24"></div> {{-- Spacer for bottom input --}} 
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
                        class="absolute left-2 top-1/2 -translate-y-1/2 p-2.5 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </button>
                    <input
                        type="text"
                        id="message-input"
                        name="message"
                        placeholder="Message Phakhaolao AI..."
                        autocomplete="off"
                        class="w-full pl-12 pr-14 py-4 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 rounded-2xl shadow-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-[15px] placeholder:text-zinc-400"
                    >
                    <button
                        type="submit"
                        id="send-btn"
                        class="absolute right-2 top-1/2 -translate-y-1/2 p-2.5 bg-zinc-900 dark:bg-indigo-600 text-white rounded-xl hover:bg-zinc-700 dark:hover:bg-indigo-500 disabled:opacity-30 disabled:hover:bg-zinc-900 dark:disabled:hover:bg-indigo-600 transition-all shadow-sm group"
                        disabled
                    >
                        <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>
                <div class="text-center mt-2">
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">Phakhaolao AI can make mistakes. Consider checking important information.</p>
                </div>
            </div>
        </footer>
    </main>
</div>

<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-zinc-900">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Delete conversation?</h3>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">This will permanently delete this conversation and all messages.</p>
        <div class="mt-5 flex items-center justify-end gap-2">
            <button id="delete-modal-cancel" type="button" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Cancel</button>
            <button id="delete-modal-confirm" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-500">Delete</button>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script>
let currentConversationId = @json($currentConversation->id ?? null);
let pendingDeleteConversationId = null;

function promptDeleteConversation(id, event) {
    if (event) event.preventDefault();
    pendingDeleteConversationId = id;
    const modal = document.getElementById('delete-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
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
            if (id === currentConversationId) {
                window.location.href = '{{ route("chat") }}';
            } else {
                window.location.reload();
            }
        }
    } catch (e) {
        console.error('Delete failed', e);
    }
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
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        }
        syncThemeToggleIcons();
    }

    syncThemeToggleIcons();
    themeToggleButtons.forEach((button) => {
        button.addEventListener('click', toggleTheme);
    });

    const chatContainer = document.getElementById('chat-container');
    const dropOverlay = document.getElementById('drop-overlay');

    let isStreaming = false;
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

    function updateSendButton() {
        sendBtn.disabled = (input.value.trim().length === 0 && !selectedImageFile) || isStreaming;
    }

    // Enable/Disable send button based on input or image
    input.addEventListener('input', updateSendButton);

    // Upload button triggers file input
    uploadBtn.addEventListener('click', () => imageInput.click());

    // Handle file selection
    const defaultPlaceholder = 'Message Phakhaolao AI...';
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

        div.innerHTML = `<div class="max-w-[85%] md:max-w-[75%] px-4 py-3 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white text-[15px] leading-relaxed shadow-sm">${content}</div>`;
        messagesContainer.appendChild(div);
        scrollToBottom();
    }

    function createAssistantBubble() {
        hideWelcome();
        const wrapper = document.createElement('div');
        wrapper.className = 'chat-message relative flex justify-start w-full gap-4 animate-fade-in group';
        wrapper.innerHTML = `
            <div class="shrink-0 w-8 h-8 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shadow-sm overflow-hidden">
                <img src="{{ asset('favicon-192.png') }}" alt="AI" class="w-6 h-6">
            </div>
            <div class="max-w-[85%] md:max-w-[85%]">
                <div class="prose prose-zinc dark:prose-invert max-w-none text-[15px] leading-relaxed">
                    <div class="typing-indicator flex gap-1 items-center py-2 px-1"><span class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce"></span><span class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce delay-100"></span><span class="w-1.5 h-1.5 bg-zinc-400 rounded-full animate-bounce delay-200"></span></div>
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
                ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'src', 'alt', 'loading', 'data-fallback-img', 'viewBox', 'fill', 'stroke', 'stroke-linecap', 'stroke-linejoin', 'stroke-width', 'd', 'width', 'height'],
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

    function renderExistingAssistantMessages() {
        document.querySelectorAll('.chat-message').forEach((messageEl) => {
            const rawEl = messageEl.querySelector('.js-assistant-raw');
            const renderedEl = messageEl.querySelector('.js-assistant-rendered');
            if (!rawEl || !renderedEl) {
                return;
            }

            renderAssistantContent(renderedEl, rawEl.value || '');
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function sendMessage(message) {
        if (isStreaming) return;
        isStreaming = true;
        sendBtn.disabled = true;
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
            if (imageFile) {
                formData.append('image', imageFile);
            }

            const response = await fetch('{{ route("chat.send") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'text/event-stream' },
                body: formData,
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
                            scrollToBottom();
                        }
                        if (parsed.conversation_id && !currentConversationId) {
                            currentConversationId = parsed.conversation_id;
                        }
                    } catch (e) {}
                }
            }

            if (fullText) {
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
                }
            }
        } catch (error) {
            console.error('Chat error:', error);
            showError(error.message || 'Something went wrong.');
            bubble.parentElement.parentElement.remove();
        } finally {
            isStreaming = false;
            input.focus();
        }
    }

    function formatText(text) {
        text = text.replace(/https:\/\/species\.phakhaolao\.la\/species\/(\d+)/gi, 'https://species.phakhaolao.la/search/specie_details/$1');
        let html = escapeHtml(text);
        html = html.replace(/```([\s\S]*?)```/g, '<pre class="bg-zinc-900 text-zinc-100 p-3 rounded-lg my-2 overflow-x-auto text-sm"><code>$1</code></pre>');
        html = html.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<div class="my-3 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm"><img src="$2" alt="$1" loading="lazy" class="max-h-80 w-auto object-contain" data-fallback-img /></div>');
        html = html.replace(/(^|[\s>])(https?:\/\/[^\s<>"']+\.(?:png|jpe?g|gif|webp)(?:\?[^\s<>"']*)?)(?=$|[\s<])/gmi, (full, prefix, url) => {
            const clean = String(url).replace(/[.,;:!?)]+$/g, '');
            return `${prefix}<div class="my-3 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm"><img src="${clean}" alt="species image" loading="lazy" class="max-h-80 w-auto object-contain" data-fallback-img /></div>`;
        });
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 underline">$1</a>');
        html = html.replace(/(^|[\s>])(https?:\/\/[^\s<>"']+)(?=$|[\s<])/gmi, (full, prefix, url) => {
            const clean = String(url).replace(/[.,;:!?)]+$/g, '');
            if (/\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(clean)) {
                return full;
            }
            return `${prefix}<a href="${clean}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 underline">${clean}</a>`;
        });
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 rounded-md bg-zinc-200 dark:bg-zinc-700 text-sm font-mono">$1</code>');
        html = html.replace(/\n/g, '<br>');
        return html;
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
                anchor.className = 'flex items-center gap-2 p-3 text-sm text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400';
                anchor.textContent = 'Image unavailable';
                wrapper.appendChild(anchor);
            });
        });
    }

    renderExistingAssistantMessages();
    enforceNewTabLinks(messagesContainer);
    handleBrokenImages(messagesContainer);

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const msg = input.value.trim();
        if (msg || selectedImageFile) sendMessage(msg);
    });

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
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e4e4e7; border-radius: 20px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #3f3f46; }
</style>
@endsection

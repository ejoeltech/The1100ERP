<!-- AI Assistant Widget -->
<div id="ai-widget-container" class="fixed bottom-6 right-6 z-50 font-sans">

    <!-- Chat Window (Hidden by default) -->
    <div id="ai-chat-window"
        class="hidden bg-white rounded-xl shadow-2xl border border-gray-200 w-80 sm:w-96 flex flex-col transition-all transform origin-bottom-right duration-200 mb-4"
        style="height: 500px; max-height: 80vh;">

        <!-- Header -->
        <div class="bg-gray-900 text-white p-4 rounded-t-xl flex justify-between items-center shadow-md">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <h3 class="font-bold text-sm">Smart Assistant</h3>
            </div>
            <button onclick="toggleAiChat()"
                class="text-gray-400 hover:text-white hover:bg-gray-700 rounded-full p-1 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <!-- Messages Area -->
        <div id="ai-messages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 text-sm">
            <!-- Welcome Message -->
            <div class="flex gap-2 items-start">
                <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <img src="<?php echo isset($base_path) ? $base_path : '.'; ?>/assets/icons/assistant.png"
                        class="w-6 h-6" alt="Bot">
                </div>
                <div
                    class="bg-white p-3 rounded-lg rounded-tl-none shadow-sm border border-gray-100 max-w-[85%] text-gray-800">
                    <p>Hello! I can help you find invoices, check products, or draft quotes. Try asking:</p>
                    <div class="mt-2 space-y-1">
                        <button onclick="sendQuickPrompt('Show me unpaid invoices')"
                            class="block w-full text-left text-xs text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded transition-colors">•
                            Show me unpaid invoices</button>
                        <button onclick="sendQuickPrompt('Top selling products')"
                            class="block w-full text-left text-xs text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded transition-colors">•
                            Top selling products</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="p-3 bg-white border-t border-gray-200 rounded-b-xl">
            <form onsubmit="handleAiSubmit(event)" class="relative">
                <input type="text" id="ai-input" placeholder="Ask me anything..."
                    class="w-full pl-4 pr-10 py-3 bg-gray-100 border-transparent focus:bg-white focus:ring-2 focus:ring-blue-500 rounded-full text-sm transition-all"
                    autocomplete="off">
                <button type="submit" id="ai-send-btn"
                    class="absolute right-2 top-2 p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-100 rounded-full transition-colors disabled:opacity-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Floating Button -->
    <button onclick="toggleAiChat()" id="ai-fab"
        class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-105 flex items-center justify-center group">
        <span class="absolute -top-1 -right-1 flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
        </span>
        <img src="<?php echo isset($base_path) ? $base_path : '.'; ?>/assets/icons/assistant.png" class="w-8 h-8"
            alt="Chat">
    </button>
</div>

<script>
    const GROQ_CONFIG = {
        chatUrl: '<?php echo isset($base_path) ? $base_path : "."; ?>/api/ai/chat.php'
    };

    function toggleAiChat() {
        const chat = document.getElementById('ai-chat-window');
        chat.classList.toggle('hidden');
        if (!chat.classList.contains('hidden')) {
            document.getElementById('ai-input').focus();
        }
    }

    function sendQuickPrompt(text) {
        const input = document.getElementById('ai-input');
        input.value = text;
        handleAiSubmit({ preventDefault: () => { } });
    }

    async function handleAiSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('ai-input');
        const message = input.value.trim();
        if (!message) return;

        // Add User Message
        addMessage(message, 'user');
        input.value = '';

        // Show Loading
        const loadingId = addLoading();

        try {
            const response = await fetch(GROQ_CONFIG.chatUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });

            const data = await response.json();
            removeMessage(loadingId);

            if (data.error) {
                addMessage(`Error: ${data.error}`, 'bot', true);
                return;
            }

            // Handle different response types
            if (data.type === 'sql' && data.data) {
                // Render Table
                let tableHtml = `<div class="overflow-x-auto"><table class="min-w-full text-xs mt-2 border border-gray-200 divide-y divide-gray-200">`;

                if (data.data.length > 0) {
                    // Headers
                    tableHtml += `<thead class="bg-gray-50"><tr>`;
                    Object.keys(data.data[0]).forEach(key => {
                        tableHtml += `<th class="px-2 py-1 text-left font-medium text-gray-500 uppercase tracking-wider">${key}</th>`;
                    });
                    tableHtml += `</tr></thead><tbody class="bg-white divide-y divide-gray-200">`;

                    // Rows
                    data.data.forEach(row => {
                        tableHtml += `<tr>`;
                        Object.values(row).forEach(val => {
                            tableHtml += `<td class="px-2 py-1 text-gray-700 whitespace-nowrap">${val}</td>`;
                        });
                        tableHtml += `</tr>`;
                    });
                    tableHtml += `</tbody></table></div>`;

                    addMessage(data.content + tableHtml, 'bot', false, true); // Allow HTML
                } else {
                    addMessage(data.content + " (No results found)", 'bot');
                }

            } else if (data.type === 'action' && data.action_url) {
                addMessage(`${data.content} <br><a href="${data.action_url}" class="inline-block mt-2 px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Go there &rarr;</a>`, 'bot', false, true);
            } else {
                // Text only
                addMessage(data.content, 'bot');
            }

        } catch (err) {
            removeMessage(loadingId);
            addMessage("Sorry, I encountered a network error.", 'bot', true);
            console.error(err);
        }
    }

    function addMessage(text, sender, isError = false, isHtml = false) {
        const container = document.getElementById('ai-messages');
        const wrapper = document.createElement('div');
        wrapper.className = `flex gap-2 items-start ${sender === 'user' ? 'flex-row-reverse' : ''}`;

        const avatar = document.createElement('div');
        avatar.className = `w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${sender === 'user' ? 'bg-gray-200 text-gray-600' : 'bg-blue-50'}`;
        if (sender === 'user') {
            avatar.innerHTML = '👤';
        } else {
            const img = document.createElement('img');
            img.src = '<?php echo isset($base_path) ? $base_path : "."; ?>/assets/icons/assistant.png';
            img.className = 'w-6 h-6';
            avatar.appendChild(img);
        }

        const bubble = document.createElement('div');
        bubble.className = `p-3 rounded-lg shadow-sm max-w-[85%] text-gray-800 ${sender === 'user' ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white border border-gray-100 rounded-tl-none'} ${isError ? 'bg-red-50 text-red-600 border-red-200' : ''}`;

        if (isHtml) {
            bubble.innerHTML = text;
        } else {
            bubble.textContent = text;
        }

        wrapper.appendChild(avatar);
        wrapper.appendChild(bubble);
        container.appendChild(wrapper);
        container.scrollTop = container.scrollHeight;

        return wrapper.id = 'msg-' + Date.now();
    }

    function addLoading() {
        const container = document.getElementById('ai-messages');
        const wrapper = document.createElement('div');
        wrapper.id = 'loading-' + Date.now();
        wrapper.className = 'flex gap-2 items-start';
        wrapper.innerHTML = `
        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0">🤖</div>
        <div class="bg-white p-3 rounded-lg rounded-tl-none shadow-sm border border-gray-100">
            <div class="flex space-x-1">
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
            </div>
        </div>
    `;
        container.appendChild(wrapper);
        container.scrollTop = container.scrollHeight;
        return wrapper.id;
    }

    function removeMessage(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }
</script>
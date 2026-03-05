document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const chatContainer = document.getElementById('chat_container');
    const messageInput = document.getElementById('msgr_input');
    const sendButton = document.getElementById('send_button');
    const emojiButtons = document.querySelectorAll('.emoji');
    const storyCircles = document.querySelectorAll('.story-circle');
    
    // Mock data
    const mockResponses = [
        "That's interesting. Tell me more.",
        "I completely understand what you mean.",
        "I hadn't thought about it that way before.",
        "That's great news!",
        "LOL 😂 That's hilarious!",
        "Really? I'm surprised to hear that.",
        "I'm not sure I agree, but I see your point.",
        "Let's discuss this further when we meet.",
        "Thanks for letting me know!",
        "Sorry to hear that. Is there anything I can do to help?",
        "Can we talk about this tomorrow? I need some time to think.",
        "Wow! That's amazing! 👍",
        "So how are you liking SmartAdmin?"
    ];
    
    const mockImages = [
        './img/demo/gallery/1.jpg',
        './img/demo/gallery/2.jpg',
        './img/demo/gallery/3.jpg',
        './img/demo/gallery/4.jpg',
        './img/demo/gallery/5.jpg',
    ];
    
    //const mockEmojis = ['👍', '❤️', '😂', '👏', '😍', '🎉', '👌', '✨'];
    
    // const mockFiles = [
    //     { name: 'Project_Brief.pdf', type: 'pdf', size: '1.2 MB' },
    //     { name: 'Meeting_Notes.docx', type: 'doc', size: '425 KB' },
    //     { name: 'Presentation.pptx', type: 'ppt', size: '3.8 MB' },
    //     { name: 'Budget.xlsx', type: 'xls', size: '890 KB' }
    // ];
    
    // Initialize Story Circles (top horizontal scrolling avatars)
    if (storyCircles.length) {
        storyCircles.forEach(circle => {
            circle.addEventListener('click', () => {
                // Deactivate all stories
                storyCircles.forEach(c => c.classList.remove('active'));
                // Activate clicked story
                circle.classList.add('active');
            });
        });
    }
    
    // Handle emoji selection
    if (emojiButtons.length) {
        emojiButtons.forEach(emoji => {
            emoji.addEventListener('click', (e) => {
                e.preventDefault();
                // Get emoji value from the data attribute or class
                const emojiType = emoji.classList.contains('emoji--like') ? '👍' :
                                  emoji.classList.contains('emoji--love') ? '❤️' :
                                  emoji.classList.contains('emoji--haha') ? '😂' :
                                  emoji.classList.contains('emoji--yay') ? '🎉' :
                                  emoji.classList.contains('emoji--wow') ? '😮' :
                                  emoji.classList.contains('emoji--sad') ? '😢' :
                                  emoji.classList.contains('emoji--angry') ? '😡' : '';
                
                if (emojiType) {
                    // Send the emoji directly as a message
                    sendMessage(emojiType);
                }
            });
        });
    }
    
    // Send message function
    function sendMessage(messageContent = null) {
        // Get message from input or passed content
        const message = messageContent || messageInput.value.trim();
        
        if (!message) return;
        
        // Clear input if using the input field
        if (!messageContent) messageInput.value = '';
        
        // Create message HTML
        const currentTime = new Date();
        const timeString = currentTime.getHours().toString().padStart(2, '0') + ':' + 
                           currentTime.getMinutes().toString().padStart(2, '0');
        
        // Add message to chat
        appendMessage(message, 'sent', timeString);
        
        // Scroll to bottom
        scrollToBottom();
        
        // Show typing indicator
        showTypingIndicator();
        
        // After a delay, show response
        const responseDelay = 1000 + Math.random() * 2000; // 1-3 seconds
        setTimeout(() => {
            hideTypingIndicator();
            generateResponse();
        }, responseDelay);
    }
    
    // Generate a random response
    function generateResponse() {
        // Decide what type of response to generate
        const responseType = Math.random();
        
        if (responseType < 0.6) { // 60% chance of text
            const randomResponse = mockResponses[Math.floor(Math.random() * mockResponses.length)];
            appendMessage(randomResponse, 'get', getCurrentTime());
        } else if (responseType < 0.7) { // 10% chance of image
            const randomImage = mockImages[Math.floor(Math.random() * mockImages.length)];
            appendImageMessage(randomImage, 'get', getCurrentTime());
        } else if (responseType < 0.9) { // 20% chance of emoji only
            const animatedEmojis = ['👍', '❤️', '😂', '🎉', '😮', '😢', '😡'];
            const randomEmoji = animatedEmojis[Math.floor(Math.random() * animatedEmojis.length)];
            appendMessage(randomEmoji, 'get', getCurrentTime());
        } 
        // else { // 10% chance of file
        //     const randomFile = mockFiles[Math.floor(Math.random() * mockFiles.length)];
        //     appendFileMessage(randomFile, 'get', getCurrentTime());
        // }
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    // Show typing indicator
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chat-segment chat-segment-get typing-indicator';
        typingDiv.innerHTML = `
            <div class="chat-message">
                <div class="typing">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        chatContainer.appendChild(typingDiv);
        scrollToBottom();
    }
    
    // Hide typing indicator
    function hideTypingIndicator() {
        const typingIndicator = document.querySelector('.typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    // Append message to chat
    function appendMessage(message, type, time) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-segment chat-segment-${type}`;
        
        // Check if message is just an emoji
        const isJustEmoji = /^(\p{Emoji}\uFE0F?)+$/u.test(message);
        
        // Check if message is one of our specific emojis
        const emojiType = message === '👍' ? 'like' :
                         message === '❤️' ? 'love' :
                         message === '😂' ? 'haha' :
                         message === '🎉' ? 'yay' :
                         message === '😮' ? 'wow' :
                         message === '😢' ? 'sad' :
                         message === '😡' ? 'angry' : null;
        
        let messageHtml;
        if (emojiType) {
            // Create the animated emoji without chat-message wrapper
            messageHtml = `
                <div class="emoji emoji--${emojiType}">
                    ${emojiType === 'like' ? 
                        `<div class="emoji__hand">
                            <div class="emoji__thumb"></div>
                        </div>` : 
                    emojiType === 'love' ? 
                        `<div class="emoji__heart"></div>` : 
                    emojiType === 'haha' ? 
                        `<div class="emoji__face">
                            <div class="emoji__eyes"></div>
                            <div class="emoji__mouth">
                                <div class="emoji__tongue"></div>
                            </div>
                        </div>` : 
                    emojiType === 'yay' ? 
                        `<div class="emoji__face">
                            <div class="emoji__eyebrows"></div>
                            <div class="emoji__mouth"></div>
                        </div>` : 
                    emojiType === 'wow' ? 
                        `<div class="emoji__face">
                            <div class="emoji__eyebrows"></div>
                            <div class="emoji__eyes"></div>
                            <div class="emoji__mouth"></div>
                        </div>` : 
                    emojiType === 'sad' ? 
                        `<div class="emoji__face">
                            <div class="emoji__eyebrows"></div>
                            <div class="emoji__eyes"></div>
                            <div class="emoji__mouth"></div>
                        </div>` : 
                    emojiType === 'angry' ? 
                        `<div class="emoji__face">
                            <div class="emoji__eyebrows"></div>
                            <div class="emoji__eyes"></div>
                            <div class="emoji__mouth"></div>
                        </div>` : ''}
                </div>
                <div class="${type === 'sent' ? 'text-end' : ''} fw-300 text-muted mt-1 fs-xs">
                    ${time}
                </div>
            `;
        } else {
            messageHtml = `
                <div class="chat-message ${isJustEmoji ? 'emoji-only' : ''}">
                    <p>${message}</p>
                </div>
                <div class="${type === 'sent' ? 'text-end' : ''} fw-300 text-muted mt-1 fs-xs">
                    ${time}
                </div>
            `;
        }
        
        messageDiv.innerHTML = messageHtml;
        chatContainer.appendChild(messageDiv);
    }
    
    // Append image message
    function appendImageMessage(imageSrc, type, time) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-segment chat-segment-${type}`;
        
        messageDiv.innerHTML = `
            <div class="chat-message">
                <p><img src="${imageSrc}" class="img-fluid rounded" alt="Shared image" style="max-height: 200px;"></p>
            </div>
            <div class="${type === 'sent' ? 'text-end' : ''} fw-300 text-muted mt-1 fs-xs">
                ${time}
            </div>
        `;
        chatContainer.appendChild(messageDiv);
    }
    
    // Append file message
    function appendFileMessage(file, type, time) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-segment chat-segment-${type}`;
        
        const fileIconClass = file.type === 'pdf' ? 'file-pdf text-danger' :
                              file.type === 'doc' ? 'file-word text-primary' :
                              file.type === 'xls' ? 'file-excel text-success' :
                              file.type === 'ppt' ? 'file-powerpoint text-warning' : 'file text-muted';
        
        messageDiv.innerHTML = `
            <div class="chat-message">
                <div class="d-flex align-items-center p-2 rounded bg-white">
                    <i class="sa sa-${fileIconClass} fs-2x me-2"></i>
                    <div class="flex-grow-1">
                        <div class="text-truncate fw-500">${file.name}</div>
                        <small class="text-muted">${file.size}</small>
                    </div>
                    <a href="javascript:void(0);" class="btn btn-sm btn-icon">
                        <i class="sa sa-download"></i>
                    </a>
                </div>
            </div>
            <div class="${type === 'sent' ? 'text-end' : ''} fw-300 text-muted mt-1 fs-xs">
                ${time}
            </div>
        `;
        chatContainer.appendChild(messageDiv);
    }
    
    // Helper to get current time string
    function getCurrentTime() {
        const now = new Date();
        return now.getHours().toString().padStart(2, '0') + ':' + 
               now.getMinutes().toString().padStart(2, '0');
    }
    
    // Scroll chat to bottom
    function scrollToBottom() {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    // Event listeners
    if (messageInput) {
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    if (sendButton) {
        sendButton.addEventListener('click', () => {
            sendMessage();
        });
    }
    
    // Initialize with some random messages
    function populateInitialChat() {
        // Clear existing messages except for timestamps
        const existingMessages = chatContainer.querySelectorAll('.chat-segment:not(.d-flex)');
        existingMessages.forEach(msg => msg.remove());
        
        // Add some initial messages
        const messages = [
            { text: "Hi there! How's your day going?", type: 'get', delay: 0 },
            { text: "Pretty good, thanks for asking! Just finished a big project.", type: 'sent', delay: 300 },
            { text: "That's great to hear! Is this the same one you mentioned last week?", type: 'get', delay: 600 },
            { text: "Yes, finally wrapped it up. The client was really happy with the results.", type: 'sent', delay: 900 },
            //{ file: mockFiles[0], type: 'sent', isFile: true, delay: 1200 },
            { text: "Thanks for sharing the document! I'll take a look at it.", type: 'get', delay: 1500 },
            { text: "Let me know if you need any clarification.", type: 'sent', delay: 1800 },
            { image: mockImages[2], type: 'get', isImage: true, delay: 2100 },
            { text: "That looks amazing! Is that from the project?", type: 'sent', delay: 2400 },
            { text: "Yes, it's the final design we went with 😊", type: 'get', delay: 2700 }
        ];
        
        let cumulativeDelay = 0;
        messages.forEach(msg => {
            setTimeout(() => {
                if (msg.isImage) {
                    appendImageMessage(msg.image, msg.type, getCurrentTime());
                } else if (msg.isFile) {
                    appendFileMessage(msg.file, msg.type, getCurrentTime());
                } else {
                    appendMessage(msg.text, msg.type, getCurrentTime());
                }
                scrollToBottom();
            }, cumulativeDelay);
            cumulativeDelay += msg.delay;
        });
    }
    
    // Add CSS for the typing indicator
    const style = document.createElement('style');
    style.textContent = `
        .typing {
            display: flex;
            align-items: center;
            height: 17px;
        }
        .typing span {
            background-color: #90949c;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            margin: 0 2px;
            display: block;
            animation: typing 1.3s infinite ease-in-out;
        }
        .typing span:nth-child(1) {
            animation-delay: 0s;
        }
        .typing span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-5px);
            }
        }
        .emoji-only {
            font-size: 3rem;
        }
        
        /* Improved conversation list styling */
        #js-slide-right .list-group-item {
            transition: background-color 0.2s ease;
            border-radius: 8px;
            margin: 4px 8px;
            border: none;
        }
        
                            
               
        .unread-badge {
            background-color: var(--primary-500);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Story circles */
        .story-circle {
            cursor: pointer;
        }
        
        .story-circle.active .profile-image {
            border-color: var(--primary-500);
        }
        
        .story-circle .profile-image {
            border: 2px solid var(--bs-body-bg);
        }
        
        .story-circle.has-story .profile-image {
            border: 2px solid var(--primary-500);
        }
        
        /* Enhanced Emoji Styles */
        .chat-segment .emoji {
            transform: scale(2);
            margin: 16px;
            display: inline-block;
        }
        
        .chat-segment-sent .emoji {
            margin-left: auto;
        }
        
        .chat-segment-get .emoji {
            margin-right: auto;
        }
        
    `;
    document.head.appendChild(style);
    
    // Initialize the chat
    populateInitialChat();
    
    // Add send button if it doesn't exist
    if (!sendButton) {
        const inputGroup = messageInput.parentElement;
        const newSendButton = document.createElement('button');
        newSendButton.id = 'send_button';
        newSendButton.className = 'btn btn-icon fs-xl width-1 flex-shrink-0';
        newSendButton.setAttribute('type', 'button');
        newSendButton.setAttribute('data-bs-toggle', 'tooltip');
        newSendButton.setAttribute('data-bs-original-title', 'Send');
        newSendButton.setAttribute('data-bs-placement', 'top');
        newSendButton.innerHTML = '<svg class="sa-icon sa-bold sa-icon-subtlelight"><use href="icons/sprite.svg#send"></use></svg>';
        
        // Append to input group instead of trying to insert before a specific element
        inputGroup.appendChild(newSendButton);
        
        // Add click handler
        newSendButton.addEventListener('click', () => {
            sendMessage();
        });
    }
    
    function insertEmoji(element) {
        // Get the emoji character from the data attribute
        const emoji = element.getAttribute('data-emoji');
        
        // Get the chat input
        const chatInput = document.getElementById('msgr_input');
        
        // Insert the emoji at cursor position or append to end
        if (chatInput) {
            const startPos = chatInput.selectionStart;
            const endPos = chatInput.selectionEnd;
            const text = chatInput.value;
            const before = text.substring(0, startPos);
            const after = text.substring(endPos, text.length);
            
            chatInput.value = before + emoji + after;
            
            // Move cursor position after the inserted emoji
            chatInput.selectionStart = startPos + emoji.length;
            chatInput.selectionEnd = startPos + emoji.length;
            
            // Focus back on the input
            chatInput.focus();
        }
        
        // Close the dropdown if it's open
        const dropdown = bootstrap.Dropdown.getInstance(element.closest('.dropdown-menu').previousElementSibling);
        if (dropdown) {
            dropdown.hide();
        }
    }
});

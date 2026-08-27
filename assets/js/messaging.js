document.addEventListener('DOMContentLoaded', () => {
  const messageForm = document.getElementById('messageForm');
  const messageInput = document.getElementById('messageInput');
  const messageStatus = document.getElementById('messageStatus');
  const chatMessages = document.getElementById('chatMessages');
  const chatBody = document.getElementById('chatBody');
  const receiverInput = document.getElementById('receiverId');

  // Track last known message ID for efficient polling
  let lastMessageId = 0;

  // Scroll to bottom of chat
  function scrollToBottom() {
    if (chatBody) {
      chatBody.scrollTop = chatBody.scrollHeight;
    }
  }

  // Initialize lastMessageId from existing messages
  if (chatMessages) {
    const rendered = chatMessages.querySelectorAll('[data-msg-id]');
    rendered.forEach(el => {
      const id = parseInt(el.dataset.msgId);
      if (id > lastMessageId) lastMessageId = id;
    });
  }

  scrollToBottom();

  // ── Date Label Helper ──────────────────────────────────────
  function getDateLabel(dateStr) {
    const date = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const sixDaysAgo = new Date(today);
    sixDaysAgo.setDate(sixDaysAgo.getDate() - 6);

    const msgDay = new Date(date);
    msgDay.setHours(0, 0, 0, 0);

    if (msgDay.getTime() === today.getTime()) return 'Today';
    if (msgDay.getTime() === yesterday.getTime()) return 'Yesterday';
    if (msgDay >= sixDaysAgo) {
      return date.toLocaleDateString([], { weekday: 'long' });
    }
    return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
  }

  // Track last rendered date for date separators
  let lastRenderedDate = '';
  if (chatMessages) {
    const separators = chatMessages.querySelectorAll('.chat-date-separator span');
    if (separators.length > 0) {
      // Get the last date separator's text content as a reference
      // But we track by actual date string from last message
      const allMsgs = chatMessages.querySelectorAll('[data-msg-id]');
      if (allMsgs.length > 0) {
        // The last message's date
        const lastMsg = allMsgs[allMsgs.length - 1];
        // We'll track via getDateLabel below during polling
      }
    }
    // Determine lastRenderedDate from the last separator
    const seps = chatMessages.querySelectorAll('.chat-date-separator span');
    if (seps.length > 0) {
      lastRenderedDate = seps[seps.length - 1].textContent.trim();
    }
  }

  // ── Append Date Separator ──────────────────────────────────
  function appendDateSeparator(label) {
    if (!chatMessages) return;
    const sep = document.createElement('div');
    sep.className = 'chat-date-separator';
    sep.innerHTML = `<span>${label}</span>`;
    chatMessages.appendChild(sep);
  }

  // --- Send Message ---
  if (messageForm) {
    messageForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const text = messageInput.value.trim();
      const receiverId = receiverInput ? parseInt(receiverInput.value) : 0;

      if (!text) {
        messageStatus.textContent = 'Please enter a message before sending.';
        return;
      }

      if (!window.currentUserId) {
        messageStatus.textContent = 'You need to be logged in to send messages.';
        return;
      }

      if (!receiverId) {
        messageStatus.textContent = 'No recipient selected.';
        return;
      }

      // Optimistically render the message
      const now = new Date();
      const timeStr = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
      const dateLabel = getDateLabel(now.toISOString());

      // Check if we need a new date separator
      if (dateLabel !== lastRenderedDate) {
        appendDateSeparator(dateLabel);
        lastRenderedDate = dateLabel;
      }

      appendMessage(text, timeStr, true, false);
      messageInput.value = '';
      messageInput.focus();
      messageStatus.textContent = 'Sending...';

      try {
        const res = await fetch('api/networking/send_message.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            receiver_id: receiverId,
            content: text
          })
        });
        const data = await res.json();

        if (data.success) {
          messageStatus.textContent = '';
          // Update lastMessageId from response
          if (data.data && data.data.message_id) {
            lastMessageId = Math.max(lastMessageId, data.data.message_id);
          }
        } else {
          messageStatus.textContent = data.message || 'Failed to send message.';
        }
      } catch (err) {
        messageStatus.textContent = 'Network error. Message may not have been delivered.';
      }
    });
  }

  // --- Append a message bubble to the chat ---
  function appendMessage(text, timeStr, isMine, isRead, msgId) {
    if (!chatMessages) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex align-items-start gap-2 chat-msg-enter' + (isMine ? ' align-self-end' : '');
    wrapper.style.maxWidth = '75%';
    if (msgId) wrapper.dataset.msgId = msgId;

    const bubble = document.createElement('div');
    bubble.className = 'p-3 rounded-3 shadow-sm' + (isMine ? ' text-white' : ' bg-white border');
    if (isMine) {
      bubble.style.backgroundColor = 'var(--signal-blue)';
    }

    const body = document.createElement('p');
    body.className = 'mb-0 small';
    body.textContent = text;

    const time = document.createElement('span');
    time.className = (isMine ? 'text-white-50' : 'text-secondary') + ' mt-1 d-block';
    time.style.fontSize = '0.68rem';

    // Add read receipt for own messages
    if (isMine) {
      const receiptClass = isRead ? 'read' : 'sent';
      time.innerHTML = `${timeStr} <span class="msg-read-receipt ${receiptClass}"><i class="bi bi-check2-all"></i></span>`;
    } else {
      time.textContent = timeStr;
    }

    bubble.appendChild(body);
    bubble.appendChild(time);
    wrapper.appendChild(bubble);
    chatMessages.appendChild(wrapper);

    scrollToBottom();
  }

  // --- Poll for new messages every 5 seconds ---
  // Now tracks by last message ID instead of count, and only appends new messages
  async function pollMessages() {
    const receiverId = receiverInput ? parseInt(receiverInput.value) : 0;
    if (!receiverId || !window.currentUserId) return;

    try {
      const res = await fetch(`api/networking/get_messages.php?user_id=${receiverId}&limit=100`);
      const data = await res.json();

      if (data.success && data.data.messages) {
        const messages = data.data.messages.reverse(); // API returns DESC, we want ASC

        // Find new messages (those with ID > lastMessageId)
        const newMessages = messages.filter(msg => msg.id > lastMessageId);

        if (newMessages.length > 0) {
          newMessages.forEach(msg => {
            const isMine = msg.sender_id == window.currentUserId;
            // Skip our own messages (already optimistically rendered)
            if (isMine) {
              // But do update the ID tracking
              lastMessageId = Math.max(lastMessageId, msg.id);
              return;
            }

            const timeStr = new Date(msg.sent_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            const dateLabel = getDateLabel(msg.sent_at);

            // Check if we need a new date separator
            if (dateLabel !== lastRenderedDate) {
              appendDateSeparator(dateLabel);
              lastRenderedDate = dateLabel;
            }

            appendMessage(msg.content, timeStr, false, false, msg.id);
            lastMessageId = Math.max(lastMessageId, msg.id);
          });
        }

        // Update read receipts for our sent messages
        const allMsgEls = chatMessages ? chatMessages.querySelectorAll('[data-msg-id]') : [];
        messages.forEach(msg => {
          if (msg.sender_id == window.currentUserId && msg.is_read) {
            allMsgEls.forEach(el => {
              if (parseInt(el.dataset.msgId) === msg.id) {
                const receipt = el.querySelector('.msg-read-receipt');
                if (receipt && receipt.classList.contains('sent')) {
                  receipt.classList.remove('sent');
                  receipt.classList.add('read');
                }
              }
            });
          }
        });
      }
    } catch (err) {
      // Silent fail on polling
    }
  }

  // Start polling if we have an active chat
  if (receiverInput && parseInt(receiverInput.value) > 0) {
    setInterval(pollMessages, 5000);
  }

  // --- Connection Request Accept/Reject (with smooth transitions) ---
  document.querySelectorAll('.conn-respond').forEach(btn => {
    btn.addEventListener('click', async () => {
      const connId = parseInt(btn.dataset.id);
      const action = btn.dataset.action;

      btn.disabled = true;

      try {
        const res = await fetch('api/networking/respond_connection.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            connection_id: connId,
            action: action
          })
        });
        const data = await res.json();

        if (data.success) {
          const row = document.getElementById('conn-' + connId);
          if (row) {
            // Phase 1: Show result with fade
            row.classList.add('responded');
            row.innerHTML = `<span class="small ${action === 'accept' ? 'text-success' : 'text-secondary'}">
              <i class="bi bi-${action === 'accept' ? 'check-circle' : 'x-circle'} me-1"></i>
              ${action === 'accept' ? 'Accepted' : 'Declined'}
            </span>`;

            // Phase 2: Collapse and remove after delay
            setTimeout(() => {
              row.classList.add('removing');
              setTimeout(() => {
                row.remove();
                // Update pending count badge
                const countBadge = document.getElementById('pendingCount');
                const card = document.getElementById('pendingRequestsCard');
                if (countBadge && card) {
                  const remaining = card.querySelectorAll('.conn-request-row').length;
                  if (remaining === 0) {
                    card.style.transition = 'opacity 0.3s ease';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                  } else {
                    countBadge.textContent = remaining;
                  }
                }
              }, 350);
            }, 1500);
          }
        } else {
          alert(data.message || 'Action failed.');
          btn.disabled = false;
        }
      } catch (err) {
        alert('Network error.');
        btn.disabled = false;
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const messageForm = document.getElementById('messageForm');
  const messageInput = document.getElementById('messageInput');
  const messageStatus = document.getElementById('messageStatus');
  const chatMessages = document.getElementById('chatMessages');
  const chatBody = document.getElementById('chatBody');
  const receiverInput = document.getElementById('receiverId');

  // Scroll to bottom of chat
  function scrollToBottom() {
    if (chatBody) {
      chatBody.scrollTop = chatBody.scrollHeight;
    }
  }
  scrollToBottom();

  // Send Message
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
      appendMessage(text, timeStr, true);
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
        } else {
          messageStatus.textContent = data.message || 'Failed to send message.';
        }
      } catch (err) {
        messageStatus.textContent = 'Network error. Message may not have been delivered.';
      }
    });
  }

  // Append a message bubble to the chat
  function appendMessage(text, timeStr, isMine) {
    if (!chatMessages) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex align-items-start gap-2' + (isMine ? ' align-self-end' : '');
    wrapper.style.maxWidth = '75%';

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
    time.textContent = timeStr;

    bubble.appendChild(body);
    bubble.appendChild(time);
    wrapper.appendChild(bubble);
    chatMessages.appendChild(wrapper);

    scrollToBottom();
  }

  // Poll for new messages every 5 seconds
  let lastMessageCount = chatMessages ? chatMessages.children.length : 0;

  async function pollMessages() {
    const receiverId = receiverInput ? parseInt(receiverInput.value) : 0;
    if (!receiverId || !window.currentUserId) return;

    try {
      const res = await fetch(`api/networking/get_messages.php?user_id=${receiverId}&limit=100`);
      const data = await res.json();

      if (data.success && data.data.messages) {
        const messages = data.data.messages.reverse(); // API returns DESC, we want ASC
        if (messages.length > lastMessageCount) {
          // New messages arrived — re-render
          chatMessages.innerHTML = '';
          messages.forEach(msg => {
            const isMine = msg.sender_id == window.currentUserId;
            const timeStr = new Date(msg.sent_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            appendMessage(msg.content, timeStr, isMine);
          });
          lastMessageCount = messages.length;
        }
      }
    } catch (err) {
      // Silent fail on polling
    }
  }

  // Start polling if we have an active chat
  if (receiverInput && parseInt(receiverInput.value) > 0) {
    setInterval(pollMessages, 5000);
  }

  // Connection Request Accept/Reject
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
            row.innerHTML = `<span class="small ${action === 'accept' ? 'text-success' : 'text-secondary'}">
              <i class="bi bi-${action === 'accept' ? 'check-circle' : 'x-circle'} me-1"></i>
              ${action === 'accept' ? 'Accepted' : 'Declined'}
            </span>`;
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

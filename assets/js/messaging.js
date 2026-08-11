document.addEventListener('DOMContentLoaded', () => {
  const conversationLinks = document.querySelectorAll('[data-conversation-id]');
  const chatMessages = document.getElementById('chatMessages');
  const chatHeaderName = document.getElementById('chatHeaderName');
  const chatHeaderSubtitle = document.getElementById('chatHeaderSubtitle');
  const messageInput = document.getElementById('messageInput');
  const messageForm = document.getElementById('messageForm');
  const messageStatus = document.getElementById('messageStatus');

  const conversationData = {
    2: {
      name: 'Sarah Jenkins',
      subtitle: 'Recruiter · Accenture Philippines',
      messages: [
        {
          sender: 'other',
          text: 'Hi Ralph! We noticed your high match score for our IT Support Specialist opening in Clark.',
          time: '10:14 AM'
        },
        {
          sender: 'me',
          text: 'Hello Sarah! Yes, I am very interested in exploring tech roles with Accenture.',
          time: '10:16 AM'
        }
      ]
    },
    3: {
      name: 'Ramon David',
      subtitle: 'Hiring Manager · SG&Co',
      messages: [
        {
          sender: 'other',
          text: 'We reviewed your resume match score and it looks promising for our finance-facing systems role.',
          time: '9:40 AM'
        }
      ]
    }
  };

  let activeConversationId = 2;

  const renderMessages = (conversationId) => {
    const data = conversationData[conversationId] || conversationData[2];
    const messages = data.messages || [];

    chatHeaderName.textContent = data.name;
    chatHeaderSubtitle.innerHTML = data.subtitle;
    chatMessages.innerHTML = '';

    messages.forEach((message) => {
      const wrapper = document.createElement('div');
      wrapper.className = message.sender === 'me'
        ? 'd-flex align-items-start gap-2 align-self-end max-w-75'
        : 'd-flex align-items-start gap-2 max-w-75';

      const bubble = document.createElement('div');
      bubble.className = message.sender === 'me'
        ? 'p-3 rounded-3 text-white shadow-sm'
        : 'p-3 rounded-3 bg-white border shadow-sm';
      bubble.style.backgroundColor = message.sender === 'me' ? 'var(--signal-blue)' : '';

      const body = document.createElement('p');
      body.className = 'mb-0 small';
      body.textContent = message.text;

      const time = document.createElement('span');
      time.className = message.sender === 'me' ? 'text-white-50 extra-small mt-1 d-block text-end' : 'text-secondary extra-small mt-1 d-block';
      time.textContent = message.time;

      bubble.appendChild(body);
      bubble.appendChild(time);
      wrapper.appendChild(bubble);
      chatMessages.appendChild(wrapper);
    });

    chatMessages.scrollTop = chatMessages.scrollHeight;
  };

  const setActiveThread = (link) => {
    conversationLinks.forEach((item) => item.classList.remove('active', 'bg-light', 'text-dark'));
    link.classList.add('active', 'bg-light', 'text-dark');
    activeConversationId = Number(link.dataset.conversationId || 2);
    renderMessages(activeConversationId);
  };

  conversationLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      setActiveThread(link);
    });
  });

  messageForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const text = messageInput.value.trim();

    if (!text) {
      messageStatus.textContent = 'Please enter a message before sending.';
      return;
    }

    if (!window.currentUserId) {
      messageStatus.textContent = 'You need to be logged in to send messages.';
      return;
    }

    const conversation = conversationData[activeConversationId] || conversationData[2];
    conversation.messages.push({
      sender: 'me',
      text,
      time: new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    });

    messageInput.value = '';
    renderMessages(activeConversationId);
    messageStatus.textContent = 'Message sent locally. Connect the backend later if you want persistence.';
  });

  renderMessages(activeConversationId);
});

const presentation = window.focusGardenPresentation || {};

const timelineElement = document.querySelector('#timeline');
const payloadViewElement = document.querySelector('#payload-view code');
const runStatusElement = document.querySelector('#run-status');
const runModeLabelElement = document.querySelector('#run-mode-label');
const demoForm = document.querySelector('#demo-form');

function formatJson(data) {
  return JSON.stringify(data, null, 2);
}

function setPayloadView(step) {
  if (!step) {
    payloadViewElement.textContent = 'Brak danych.';
    return;
  }

  const preview = {};

  if (step.request) {
    preview.request = step.request;
  }

  if (step.response) {
    preview.response = step.response;
  }

  payloadViewElement.textContent = formatJson(preview);
}

function renderTimeline(result) {
  timelineElement.innerHTML = '';

  const steps = result.timeline || [];

  if (!steps.length) {
    timelineElement.innerHTML = '<p class="empty-state">Brak krokow do pokazania.</p>';
    setPayloadView(null);
    return;
  }

  steps.forEach((step, index) => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = `timeline-item state-${step.state || 'ready'}`;
    item.innerHTML = `
      <span class="timeline-marker">${String(index + 1).padStart(2, '0')}</span>
      <span class="timeline-copy">
        <strong>${step.title}</strong>
        <span>${step.detail}</span>
      </span>
    `;
    item.addEventListener('click', () => {
      document.querySelectorAll('.timeline-item').forEach((element) => {
        element.classList.remove('is-active');
      });
      item.classList.add('is-active');
      setPayloadView(step);
    });
    timelineElement.appendChild(item);
  });

  const first = timelineElement.querySelector('.timeline-item');
  if (first) {
    first.click();
  }
}

function collectFormData(mode) {
  const formData = new FormData(demoForm);
  const payload = Object.fromEntries(formData.entries());
  payload.mode = mode;
  return payload;
}

async function runWorkflow(mode) {
  runStatusElement.textContent = mode === 'live' ? 'Uruchamianie live flow...' : 'Uruchamianie demo flow...';
  runModeLabelElement.textContent = mode === 'live' ? 'Tryb live' : 'Tryb demo';

  try {
    const response = await fetch('run.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(collectFormData(mode)),
    });

    const result = await response.json();

    renderTimeline(result);

    if (result.status === 'error') {
      runStatusElement.textContent = 'Flow zakonczyl sie bledem';
      return;
    }

    runStatusElement.textContent = mode === 'live' ? 'Live flow zakonczony' : 'Demo flow zakonczony';
  } catch (error) {
    runStatusElement.textContent = 'Nie udalo sie uruchomic flow';
    payloadViewElement.textContent = String(error);
  }
}

document.querySelectorAll('[data-run-mode]').forEach((button) => {
  button.addEventListener('click', () => {
    const mode = button.getAttribute('data-run-mode') || 'simulate';
    runWorkflow(mode);
  });
});

document.querySelectorAll('.reveal').forEach((element, index) => {
  element.style.animationDelay = `${index * 120}ms`;
});

renderTimeline({
  timeline: [
    {
      title: 'Showcase ready',
      state: 'ready',
      detail: 'Ten panel pokazuje request payload, poll statusu i dispatch order.',
      request: {
        mode: 'simulate',
        organization_id: presentation.defaults?.organization_id,
      },
    },
  ],
});

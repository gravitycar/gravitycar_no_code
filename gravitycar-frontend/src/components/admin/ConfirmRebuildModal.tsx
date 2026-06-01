import React, { useState, useEffect } from 'react';
import Modal from '../ui/Modal';
import { handleAuthError } from '../../utils/authError';

// ---------------------------------------------------------------------------
// Local types — match CacheStepResult::toArray() and CacheRebuildResult::toArray()
// ---------------------------------------------------------------------------

interface CacheStepResultData {
  stepName: string;
  component: string;
  status: 'in_progress' | 'success' | 'failed' | 'skipped';
  errorMessage: string | null;
}

interface DoneEvent {
  done: true;
  success: boolean;
  message: string;
  steps?: CacheStepResultData[];
}

interface RebuildOptions {
  components: string[];
  updateSchema: boolean;
  updatePermissions: boolean;
}

export interface ConfirmRebuildModalProps {
  isOpen: boolean;
  onClose: () => void;
  options: RebuildOptions;
}

type ModalView = 'confirm' | 'streaming' | 'result';

// ---------------------------------------------------------------------------
// Module-level constants (not re-created on every render)
// ---------------------------------------------------------------------------

const COMPONENT_LABELS: Record<string, string> = {
  metadata:   'Metadata Cache',
  routes:     'API Routes Cache',
  docs:       'Documentation Cache',
  navigation: 'Navigation Cache',
};

const STEP_LABELS: Record<string, string> = {
  archive:             'Creating archive',
  clear:               'Clearing cache',
  rebuild:             'Rebuilding cache',
  validate:            'Validating cache',
  schema_update:       'Updating schema',
  permissions_update:  'Updating permissions',
  restore:             'Restoring archive',
};

// ---------------------------------------------------------------------------
// StepRow sub-component — defined outside ConfirmRebuildModal to avoid
// React reconciliation issues from a new function reference on every render
// ---------------------------------------------------------------------------

function StepRow({ step }: { step: CacheStepResultData }): JSX.Element {
  const label = STEP_LABELS[step.stepName] ?? step.stepName;

  const statusIcon = (): JSX.Element => {
    switch (step.status) {
      case 'in_progress':
        return (
          <span className="flex-shrink-0 w-5 h-5 mr-2">
            <svg
              className="animate-spin text-blue-500 w-5 h-5"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
              />
            </svg>
          </span>
        );
      case 'success':
        return (
          <span className="flex-shrink-0 w-5 h-5 mr-2 text-green-600 font-bold" aria-label="success">
            ✓
          </span>
        );
      case 'failed':
        return (
          <span className="flex-shrink-0 w-5 h-5 mr-2 text-red-600 font-bold" aria-label="failed">
            ✗
          </span>
        );
      case 'skipped':
        return (
          <span className="flex-shrink-0 w-5 h-5 mr-2 text-gray-400" aria-label="skipped">
            —
          </span>
        );
      default:
        return <span className="w-5 h-5 mr-2" />;
    }
  };

  return (
    <li className="flex flex-col py-1">
      <div className="flex items-center text-sm text-gray-800">
        {statusIcon()}
        <span>{label} ({step.component})</span>
      </div>
      {step.errorMessage && (
        <p className="ml-7 text-sm text-red-500">{step.errorMessage}</p>
      )}
    </li>
  );
}

// ---------------------------------------------------------------------------
// ConfirmRebuildModal — three-view modal: confirm → streaming → result
// ---------------------------------------------------------------------------

const ConfirmRebuildModal: React.FC<ConfirmRebuildModalProps> = ({
  isOpen,
  onClose,
  options,
}) => {
  const [view, setView]                   = useState<ModalView>('confirm');
  const [steps, setSteps]                 = useState<CacheStepResultData[]>([]);
  const [resultMessage, setResultMessage] = useState<string>('');
  const [resultSuccess, setResultSuccess] = useState<boolean>(false);

  // Reset state when modal opens so a re-open always starts fresh
  useEffect(() => {
    if (!isOpen) return;
    setView('confirm');
    setSteps([]);
    setResultMessage('');
    setResultSuccess(false);
  }, [isOpen]);

  // Disable backdrop click while streaming to prevent accidental dismissal
  const handleClose = view === 'streaming' ? () => {} : onClose;

  // -------------------------------------------------------------------------
  // SSE event parsing — pure helper, extracted for clarity
  // -------------------------------------------------------------------------

  function processEvent(rawEvent: string): void {
    if (!rawEvent.startsWith('data:')) return;

    const jsonStr = rawEvent.replace(/^data:\s*/, '');
    let parsed: CacheStepResultData | DoneEvent;
    try {
      parsed = JSON.parse(jsonStr);
    } catch {
      return; // Malformed event — skip silently
    }

    if ((parsed as DoneEvent).done === true) {
      const doneEvent = parsed as DoneEvent;
      setResultMessage(doneEvent.message);
      setResultSuccess(doneEvent.success);
      setView('result');
      return;
    }

    // Regular step event — upsert by (stepName, component) so in_progress
    // transitions to success/failed in place rather than appearing twice
    const stepData = parsed as CacheStepResultData;
    setSteps(prev => {
      const existingIdx = prev.findIndex(
        s => s.stepName === stepData.stepName && s.component === stepData.component
      );
      if (existingIdx !== -1) {
        const updated = [...prev];
        updated[existingIdx] = stepData;
        return updated;
      }
      return [...prev, stepData];
    });
  }

  // -------------------------------------------------------------------------
  // handleConfirm — fires the POST and reads the SSE stream
  // -------------------------------------------------------------------------

  async function handleConfirm(): Promise<void> {
    try {
      setView('streaming');
      setSteps([]);

      const token   = localStorage.getItem('auth_token');
      const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8081';

      const response = await fetch(`${baseUrl}/admin/cache/rebuild`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify(options),
      });

      // Check HTTP status BEFORE reading stream body
      if (response.status === 401 || response.status === 403) {
        handleAuthError(response.status);
        return;
      }

      if (!response.ok || !response.body) {
        setResultMessage(`Unexpected error: HTTP ${response.status}`);
        setResultSuccess(false);
        setView('result');
        return;
      }

      // Read the SSE stream chunk-by-chunk
      const reader  = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer    = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        // SSE events are delimited by double newlines
        const events = buffer.split('\n\n');
        buffer = events.pop() ?? ''; // keep the last partial chunk in buffer

        for (const event of events) {
          processEvent(event.trim());
        }
      }
    } catch {
      // Network error, failed fetch, or mid-stream reader failure
      setResultMessage('Network error — could not reach server.');
      setResultSuccess(false);
      setView('result');
    }
  }

  // -------------------------------------------------------------------------
  // Modal title varies by view
  // -------------------------------------------------------------------------

  const modalTitle = (): string => {
    if (view === 'confirm')   return 'Confirm Cache Rebuild';
    if (view === 'streaming') return 'Rebuilding Cache...';
    return resultSuccess ? 'Rebuild Complete' : 'Rebuild Failed';
  };

  // -------------------------------------------------------------------------
  // View renderers
  // -------------------------------------------------------------------------

  const renderConfirmView = (): JSX.Element => (
    <div>
      <p className="text-sm font-medium text-gray-700 mb-2">The following will be rebuilt:</p>
      <ul className="list-disc list-inside mb-4 text-sm text-gray-800 space-y-1">
        {options.components.map(component => (
          <li key={component}>{COMPONENT_LABELS[component] ?? component}</li>
        ))}
      </ul>

      {(options.updateSchema || options.updatePermissions) && (
        <div className="mb-4">
          <p className="text-sm font-medium text-gray-700 mb-1">Additional operations:</p>
          <ul className="list-disc list-inside text-sm text-gray-800 space-y-1">
            {options.updateSchema && (
              <li>Database schema will be updated</li>
            )}
            {options.updatePermissions && (
              <li>Permissions will be rebuilt</li>
            )}
          </ul>
        </div>
      )}

      <p className="text-sm text-gray-500 mb-6">
        A backup archive will be created before clearing any files.
      </p>

      <div className="flex justify-end gap-3">
        <button
          type="button"
          onClick={onClose}
          className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={handleConfirm}
          className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors"
        >
          Rebuild Cache
        </button>
      </div>
    </div>
  );

  const renderStreamingView = (): JSX.Element => (
    <div>
      <div className="flex justify-center mb-4">
        <svg
          className="animate-spin text-blue-500 w-8 h-8"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path
            className="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
          />
        </svg>
      </div>

      {steps.length === 0 ? (
        <p className="text-sm text-gray-500 text-center">Starting rebuild...</p>
      ) : (
        <ul className="space-y-1">
          {steps.map((step, idx) => (
            <StepRow key={`${step.stepName}-${step.component}-${idx}`} step={step} />
          ))}
        </ul>
      )}
    </div>
  );

  const renderResultView = (): JSX.Element => (
    <div>
      <div className="flex justify-center mb-4">
        {resultSuccess ? (
          <span className="text-green-600 text-5xl" aria-label="success">✓</span>
        ) : (
          <span className="text-red-600 text-5xl" aria-label="failed">✗</span>
        )}
      </div>

      <p className="text-sm text-center text-gray-700 mb-4">{resultMessage}</p>

      {steps.length > 0 && (
        <ul className="space-y-1 mb-6">
          {steps.map((step, idx) => (
            <StepRow key={`${step.stepName}-${step.component}-${idx}`} step={step} />
          ))}
        </ul>
      )}

      <div className="flex justify-end">
        <button
          type="button"
          onClick={onClose}
          className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
        >
          Close
        </button>
      </div>
    </div>
  );

  // -------------------------------------------------------------------------
  // Render
  // -------------------------------------------------------------------------

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={modalTitle()} size="lg">
      {view === 'confirm'   && renderConfirmView()}
      {view === 'streaming' && renderStreamingView()}
      {view === 'result'    && renderResultView()}
    </Modal>
  );
};

export default ConfirmRebuildModal;

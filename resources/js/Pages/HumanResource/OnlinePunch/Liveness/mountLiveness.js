import React from 'react';
import { createRoot } from 'react-dom/client';
import LivenessCheck from './LivenessCheck.jsx';

/**
 * Mounts the React-based AWS Face Liveness component into a plain DOM node.
 * AWS Amplify's FaceLivenessDetector has no Vue SDK — this is the bridge
 * that lets the rest of the app stay pure Vue.
 *
 * @returns {Function} unmount function — call on Vue onUnmounted / when done
 */
export function mountLivenessCheck(domEl, { region, identityPoolId, sessionId, onAnalysisComplete, onError, onUserCancel }) {
  const root = createRoot(domEl);

  root.render(
    React.createElement(LivenessCheck, {
      region,
      identityPoolId,
      sessionId,
      onAnalysisComplete,
      onError,
      onUserCancel,
    })
  );

  return () => root.unmount();
}

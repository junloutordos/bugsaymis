import React, { useEffect, useState } from 'react';
import { Amplify } from 'aws-amplify';
import { FaceLivenessDetector } from '@aws-amplify/ui-react-liveness';
import '@aws-amplify/ui-react/styles.css';

let configured = false;

function configureAmplify(identityPoolId, region) {
  if (configured) return;
  Amplify.configure({
    Auth: {
      Cognito: {
        identityPoolId,
        allowGuestAccess: true,
      },
    },
  });
  configured = true;
  void region;
}

export default function LivenessCheck({ region, identityPoolId, sessionId, onAnalysisComplete, onError, onUserCancel }) {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    configureAmplify(identityPoolId, region);
    setReady(true);
  }, [identityPoolId, region]);

  if (!ready) return null;

  return (
    <FaceLivenessDetector
      sessionId={sessionId}
      region={region}
      disableStartScreen
      onAnalysisComplete={onAnalysisComplete}
      onError={(error) => onError?.(error)}
      onUserCancel={() => onUserCancel?.()}
    />
  );
}

import { Navigate } from 'react-router-dom';

/** Root index redirects to dashboard */
export default function IndexPage() {
  return <Navigate to="/" replace />;
}

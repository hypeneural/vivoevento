import { useParams } from 'react-router-dom';

import EventPlayManagerPage from './pages/EventPlayManagerPage';
import PlayHubPage from './pages/PlayHubPage';

export default function PlayPage() {
  const { id } = useParams<{ id?: string }>();

  return id ? <EventPlayManagerPage /> : <PlayHubPage />;
}

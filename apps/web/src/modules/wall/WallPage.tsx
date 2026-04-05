import { useParams } from 'react-router-dom';

import EventWallManagerPage from './pages/EventWallManagerPage';
import WallHubPage from './pages/WallHubPage';

export default function WallPage() {
  const { id } = useParams<{ id?: string }>();

  return id ? <EventWallManagerPage /> : <WallHubPage />;
}

export interface Result {
  error: boolean;
  messages: string[];
  body?: {
    result: Show[];
    pagination: Pagination;
    episodes: Episode[];
    shows: Show[];
  };
}

export interface Show {
  id: number;
  name: string;
  imageMedium: string;
}

export interface Episode {
  id: number;
  showId: number;
  showName: string;
  episodeName: string;
  seasonNumber: number;
  episodeNumber: number;
  episodeSummary: string;
  type: string;
  airstamp: string;
  image: string;
  networkName: string;
  webChannelName: string;
}
export interface Pagination {
  page: number;
  totalPages: number;
  showCount: number;
  genre?: string;
  sort?: string;
}

export type notificationType = 'alert-info' | 'alert-error' | 'alert-success';

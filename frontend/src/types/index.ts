export interface User {
  id: string;
  fullName: string;
  email: string;
  firstName: string;
  lastName: string;
  isActive: boolean;
  createdAt: string;
}

export interface Event {
  id: string;
  title: string;
  description?: string;
  startTime: string;
  endTime?: string;
  status: string;
  priority: string;
  location?: string;
  lunchProvided: boolean;
  participantsCount: number;
}
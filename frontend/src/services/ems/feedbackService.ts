import { emsHttp } from './emsClient';

export interface FeedbackComment {
  uuid: string;
  is_anonymous: boolean;
  author_name: string;
  overall_rating: number;
  text_feedback: string | null;
  created_at: string;
}

export interface FeedbackSummary {
  average_overall_rating: number;
  average_organization_rating: number;
  average_program_rating: number;
  average_venue_rating: number;
  total_responses: number;
  response_rate: number;
  comments: FeedbackComment[];
}

export const feedbackService = {
  /** GET /ems/events/{event}/feedback */
  eventFeedback(eventUuid: string): Promise<FeedbackSummary> {
    return emsHttp.get<FeedbackSummary>(`/events/${eventUuid}/feedback`);
  },
};

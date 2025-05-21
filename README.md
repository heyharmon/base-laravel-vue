# LLM Mention Tracker

This project implements a backend system for tracking mentions in Large Language Model (LLM) responses to specific prompts across multiple providers. It functions as a Google Alerts-like analytics system tailored for LLMs, enabling users to monitor keyword occurrences and analyze response data.

## Features

- **Keyword Tracking**: Monitor specific keywords in LLM responses.
- **Prompt Management**: Store and execute prompts against multiple LLM providers.
- **Response Analysis**: Record and analyze responses from various LLMs.
- **Scheduled Execution**: Run prompts daily via a scheduled command.
- **Analytics**: Retrieve statistics and time-series data for keyword occurrences.

## Implementation Details

### Models
- **Keyword**: Stores keywords to track in LLM responses.
- **Prompt**: Manages prompts to be executed against LLMs.
- **Response**: Stores responses from different LLM providers.

### Pivot Relationships
- **keyword_prompt**: Tracks keyword occurrences with attributes:
  - `count`: Number of times the keyword was found.
  - `last_found_at`: Timestamp of the most recent occurrence.
- **keyword_response**: Records which keywords were detected in a response.

### Controllers (API Endpoints)
- **CRUD Operations**: Full Create, Read, Update, Delete functionality for all models.
- **PromptRunController**: Manages execution of prompts against LLMs.
- **AnalyticsController**: Provides statistics and time-series data for keyword tracking.

### Services
- **PromptRunnerService**: Orchestrates running prompts across multiple LLM providers, including:
  - OpenAI
  - Anthropic
  - Gemini
  - XAI
  - DeepSeek

### Commands

## Usage
1. Configure the system with your preferred LLM provider APIs.
2. Define keywords and prompts via the API or interface.
3. Use the `PromptRunController` to trigger prompt executions manually or rely on the `RunDailyPrompts` command for automated runs.
4. Retrieve analytics through the `AnalyticsController` to monitor keyword trends and response data.

## Future Enhancements
- Add support for additional LLM providers.
- Implement real-time alerts for keyword detections.
- Enhance analytics with more granular filtering and visualization options.